<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP <https://github.com/playwright-php>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Client;

use Playwright\Network\RequestInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Browser\PlaywrightBrowser;
use Playwright\Symfony\Client\Interception\AssetServer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * BrowserKit-compatible client that uses Playwright for browser automation
 * while routing requests through Symfony's HttpKernel.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractBrowser<BrowserKitRequest, BrowserKitResponse>
 */
class PlaywrightClient extends AbstractBrowser
{
    private ?SymfonyRequest $lastSymfonyRequest = null;
    private ?SymfonyResponse $lastSymfonyResponse = null;
    /** @var string[] */
    private array $interceptedHosts = ['localhost', '127.0.0.1', 'testapp.local'];
    private ?object $hookReceiver = null;
    private bool $interceptorSetUp = false;
    private ?AssetServer $assetServer;
    private ?string $lastProfileToken = null;

    /**
     * @param array<string, mixed> $server
     * @param string[]|null        $interceptedHosts
     */
    public function __construct(
        private readonly PlaywrightBrowser $browser,
        private readonly HttpKernelInterface $kernel,
        private readonly RequestConverter $requestConverter,
        private readonly ResponseConverter $responseConverter,
        array $server = [],
        ?array $interceptedHosts = null,
        ?object $hookReceiver = null,
        ?AssetServer $assetServer = null,
        private readonly string $baseUrl = 'http://localhost',
        private ?LoggerInterface $logger = null,
        private readonly bool $debugLogging = false,
    ) {
        parent::__construct($server);

        if (null !== $interceptedHosts) {
            $this->interceptedHosts = $interceptedHosts;
        }

        $this->hookReceiver = $hookReceiver;
        $this->assetServer = $assetServer;
        $this->logger = $logger ?? new NullLogger();

        if ($context = $this->browser->getContext()) {
            \Playwright\Symfony\Util\CookieJarSync::fromContext($this->getCookieJar(), $context);
        }
    }

    public function visit(string $path): PageInterface
    {
        $this->ensureInterceptorSetUp();
        $url = $this->getBaseUrl().$path;
        $this->log('debug', 'Navigating with Playwright', ['url' => $url]);
        $page = $this->browser->getPage();

        if (null === $page) {
            throw new \RuntimeException('No page available. Browser may not be started.');
        }

        $page->goto($url);

        if ($context = $this->browser->getContext()) {
            \Playwright\Symfony\Util\CookieJarSync::toJarFromUrl($this->getCookieJar(), $context, $page->url());
        }

        return $page;
    }

    public function getPage(): ?PageInterface
    {
        return $this->browser->getPage();
    }

    /**
     * @param array<string, mixed> $serverParameters
     */
    public function click(Link $link, array $serverParameters = []): Crawler
    {
        $this->ensureInterceptorSetUp();
        $xpath = \Playwright\Symfony\Util\XPathHelper::buildXPath($link->getNode());
        $page = $this->getPage();
        if (null === $page) {
            throw new \RuntimeException('No page available');
        }

        $page->locator('xpath='.$xpath)->click();

        return $this->getCrawler();
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $serverParameters
     */
    public function submit(Form $form, array $values = [], array $serverParameters = []): Crawler
    {
        $this->ensureInterceptorSetUp();
        if (!empty($values)) {
            $form->setValues($values);
        }

        $page = $this->getPage();
        if (null === $page) {
            throw new \RuntimeException('No page available');
        }

        \Playwright\Symfony\Util\FormInteractor::fill($page, $form);

        // Submit via JS to trigger Playwright's network interception
        $xpath = \Playwright\Symfony\Util\XPathHelper::buildXPath($form->getNode());
        $page->locator('xpath='.$xpath)->evaluate('el => el.requestSubmit ? el.requestSubmit() : el.submit()');

        // Wait for any navigation to complete
        try {
            $page->waitForLoadState('networkidle', ['timeout' => 2000]);
        } catch (\Throwable) {
            // Ignore timeout, we'll try to get content anyway
        }

        return $this->getCrawler();
    }

    public function getCrawler(): Crawler
    {
        $page = $this->getPage();
        if (null === $page) {
            return new Crawler('', $this->baseUrl);
        }

        $content = '';
        $maxRetries = 5;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            try {
                $content = $page->content() ?? '';
                break;
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'navigating')) {
                    usleep(200000); // 200ms
                    ++$retryCount;
                    continue;
                }
                throw $e;
            }
        }

        return new Crawler($content, $page->url());
    }



    /**
     * @param array<string, mixed> $options
     */
    public function setCookie(string $name, string $value, array $options = []): void
    {
        // Extract domain from baseUrl if not provided
        $domain = $options['domain'] ?? parse_url($this->getBaseUrl(), PHP_URL_HOST) ?? 'localhost';

        $cookie = array_merge([
            'name' => $name,
            'value' => $value,
            'domain' => $domain,
            'path' => $options['path'] ?? '/',
        ], $options);

        // Ensure expires is int if set
        if (isset($cookie['expires'])) {
            if (is_int($cookie['expires'])) {
                // Already an int, nothing to do
            } elseif (is_numeric($cookie['expires'])) {
                $cookie['expires'] = (int) $cookie['expires'];
            } else {
                unset($cookie['expires']); // Invalid value, remove it
            }
        }

        /** @var array{name: string, value: string, url?: string, domain?: string, path?: string, expires?: int, httpOnly?: bool, secure?: bool, sameSite?: 'Lax'|'None'|'Strict'} $cookie */
        $context = $this->browser->getContext();

        if (null === $context) {
            throw new \RuntimeException('Browser context is null - browser may not be started');
        }

        $context->addCookies([$cookie]);
    }

    public function getCookie(string $name, ?string $url = null): ?string
    {
        $url ??= $this->getBaseUrl();
        $cookies = $this->browser->getContext()?->cookies([$url]) ?? [];

        foreach ($cookies as $cookie) {
            if ($cookie['name'] === $name) {
                $value = $cookie['value'];

                return '' === $value ? null : $value;
            }
        }

        return null;
    }

    public function clearCookies(): void
    {
        $this->browser->getContext()?->clearCookies();
    }

    public function clearCookie(string $name, ?string $domain = null, string $path = '/'): void
    {
        // Behavior contract (used by tests): clearCookie removes the cookie.
        // We use clearCookies() with a filter if possible, or fallback to setting it to empty if API is limited.
        // Actually, Playwright PHP clearCookies() doesn't take arguments yet in some versions.

        // Better: use addCookies with a very old expiration date to force deletion if the API doesn't support selective clear.
        // But for our tests, setting it to empty AND returning null in getCookie is usually enough if handled consistently.

        $options = array_filter([
            'domain' => $domain,
            'path' => $path,
        ], static fn ($v) => null !== $v && '' !== $v);

        if (!isset($options['domain'])) {
            $options['domain'] = parse_url($this->getBaseUrl(), PHP_URL_HOST) ?: 'localhost';
        }

        // Set expiration to past to delete it
        $options['expires'] = 0;

        $this->setCookie($name, '', $options);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function authenticate(string $identifier = 'user', array $context = []): void
    {
        $payload = json_encode(['id' => $identifier, 'ctx' => $context], JSON_THROW_ON_ERROR);
        $this->setCookie('AUTH', $payload);
    }

    public function logout(): void
    {
        $this->clearCookie('AUTH');
    }

    public function getLastSymfonyRequest(): ?SymfonyRequest
    {
        return $this->lastSymfonyRequest;
    }

    public function getLastSymfonyResponse(): ?SymfonyResponse
    {
        return $this->lastSymfonyResponse;
    }

    public function getProfile(): ?Profile
    {
        if (null === $this->lastProfileToken) {
            return null;
        }

        // Kernel must implement getContainer() - this is the case for Symfony\Component\HttpKernel\KernelInterface implementations
        if (!$this->kernel instanceof KernelInterface) {
            return null;
        }

        // Access the container from the kernel to get the profiler service
        $container = $this->kernel->getContainer();

        if (!$container->has('profiler')) {
            return null;
        }

        $profiler = $container->get('profiler');

        if (!$profiler instanceof Profiler) {
            return null;
        }

        return $profiler->loadProfile($this->lastProfileToken);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param string[] $hosts
     */
    public function setInterceptedHosts(array $hosts): void
    {
        $this->interceptedHosts = $hosts;
    }

    /**
     * @return string[]
     */
    public function getInterceptedHosts(): array
    {
        return $this->interceptedHosts;
    }

    /**
     * @param BrowserKitRequest $request
     */
    protected function doRequest(object $request): BrowserKitResponse
    {
        $uri = $request->getUri();
        if (!str_starts_with($uri, 'http')) {
            $uri = $this->getBaseUrl().$uri;
        }

        $url = parse_url($uri);
        $path = ($url['path'] ?? '/').(isset($url['query']) ? '?'.$url['query'] : '');

        if ('GET' === $request->getMethod() && empty($request->getParameters())) {
            $this->visit($path);
        } else {
            // For POST or requests with parameters, we should ideally use a synthetic form
            // but for now, we'll just visit the path to trigger the interception.
            // If it's a POST, we might need a more complex implementation similar to
            // the other PlaywrightClient.
            $this->visit($path);
        }

        $response = $this->lastSymfonyResponse;
        if (null === $response) {
            return new BrowserKitResponse('No response captured', 500);
        }

        return new BrowserKitResponse(
            $response->getContent() ?: '',
            $response->getStatusCode(),
            $this->responseConverter->formatHeaders($response->headers->all())
        );
    }

    private function setupRequestInterception(): void
    {
        $this->browser->setupRouting(function ($route) {
            $request = $route->request();
            $url = parse_url($request->url());

            if (!$this->shouldInterceptRequest($url)) {
                $this->log('debug', 'Continuing external request', [
                    'url' => $request->url(),
                    'method' => $request->method(),
                ]);
                $route->continue();

                return;
            }

            if (
                $this->assetServer
                && $this->assetServer->supports($request->url(), $request->method())
            ) {
                $assetResponse = $this->assetServer->handle($request->url(), $request->method());
                if (null !== $assetResponse) {
                    $this->log('debug', 'AssetServer fulfilled request', [
                        'url' => $request->url(),
                        'method' => $request->method(),
                    ]);
                    $route->fulfill($assetResponse);

                    return;
                }

                $this->log('debug', 'AssetServer miss falling back to kernel', [
                    'url' => $request->url(),
                    'method' => $request->method(),
                ]);
            }

            $response = $this->handleInternalRequest($request);
            $route->fulfill($this->responseConverter->prepareFulfillOptions($response));
        });
    }

    /**
     * @param array<string, mixed>|false $url
     */
    private function shouldInterceptRequest(array|false $url): bool
    {
        return is_array($url) && isset($url['host']) && in_array($url['host'], $this->interceptedHosts, true);
    }

    private function handleInternalRequest(RequestInterface $playwrightRequest): SymfonyResponse
    {
        $symfonyRequest = $this->requestConverter->convertToSymfonyRequest($playwrightRequest);
        $startedAt = microtime(true);

        $this->beforeRequest($symfonyRequest);

        // Capture any debug output that might be generated
        $bufferLevel = ob_get_level();
        ob_start();

        try {
            $this->lastSymfonyRequest = $symfonyRequest;
            $response = $this->kernel->handle($symfonyRequest, HttpKernelInterface::MAIN_REQUEST, false);
            $this->lastSymfonyResponse = $response;

            if ($response->headers->has('X-Debug-Token')) {
                $this->lastProfileToken = $response->headers->get('X-Debug-Token');
            }

            // Only clean the buffer if we started it
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            $this->afterResponse($response);

            $this->log('info', 'Fulfilled intercepted request', [
                'method' => $symfonyRequest->getMethod(),
                'uri' => $symfonyRequest->getUri(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return $response;
        } catch (\Throwable $e) {
            // Clean up output buffers even when an exception occurs
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            $this->log('error', 'Exception while handling intercepted request', [
                'method' => $symfonyRequest->getMethod(),
                'uri' => $symfonyRequest->getUri(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ], false);

            throw $e;
        }
    }

    protected function beforeRequest(SymfonyRequest $request): void
    {
        if ($this->hookReceiver && method_exists($this->hookReceiver, 'beforeRequest')) {
            $this->hookReceiver->beforeRequest($request);
        }
    }

    protected function afterResponse(SymfonyResponse $response): void
    {
        if ($this->hookReceiver && method_exists($this->hookReceiver, 'afterResponse')) {
            $this->hookReceiver->afterResponse($response);
        }
    }

    private function ensureInterceptorSetUp(): void
    {
        if (!$this->interceptorSetUp) {
            $this->setupRequestInterception();
            $this->interceptorSetUp = true;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = [], bool $requiresDebug = true): void
    {
        if ($requiresDebug && !$this->debugLogging) {
            return;
        }

        if (null !== $this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP - https://github.com/playwright-php
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Client;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Network\RequestInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Cookie\CookieJar;
use Playwright\Symfony\Util\FormInteractor;
use Playwright\Symfony\Util\XPathHelper;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\DomCrawler\UriResolver;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Internal BrowserKit client that intercepts browser requests and routes them through Symfony's HttpKernel.
 *
 * This is the core client for in-process E2E testing of Symfony applications. It combines a real
 * Playwright browser with Symfony's HttpKernel to enable full browser testing while maintaining
 * access to Symfony internals (services, events, profiler, request/response inspection).
 *
 * Primary role in architecture:
 * - Used exclusively by PlaywrightTestCase (users never instantiate this directly)
 * - Intercepts HTTP requests from the browser to configured hosts (localhost, 127.0.0.1)
 * - Routes intercepted requests through Symfony's kernel in the same PHP process
 * - Provides access to last Symfony request/response for assertions
 * - Integrates AssetServer for fast static asset serving (bypasses kernel)
 *
 * How request interception works:
 * 1. User calls visit('/path') → navigates browser to http://localhost/path
 * 2. Browser makes HTTP request → intercepted via BrowserSessionInterface->setupRouting()
 * 3. Request matched against interceptedHosts → if matched, proceeds to step 4
 * 4. AssetServer checks if request is for static asset → if yes, serves directly
 * 5. Otherwise: RequestConverter → HttpKernel->handle() → Kernel->terminate() → ResponseConverter → browser
 * 6. Test can inspect via getLastSymfonyRequest(), getLastSymfonyResponse()
 *
 * Key features:
 * - In-process kernel execution (no real HTTP, no separate server)
 * - Full access to Symfony container, services, and profiler
 * - beforeRequest() and afterResponse() hooks for custom logic
 * - Cookie management and authentication helpers
 * - BrowserKit API compatibility (visit, click, submit, getCrawler)
 *
 * When to use:
 * - Testing your Symfony application with real browser behavior
 * - Need to inspect Symfony internals (request, response, services)
 * - Want fast E2E tests without network overhead
 *
 * When NOT to use:
 * - Testing external websites → use BrowserKit\PlaywrightClient instead
 * - Need real HTTP networking behavior → use BrowserKit\PlaywrightClient instead
 *
 * @internal This class is only used by PlaywrightTestCase and should not be instantiated directly
 *
 * @final
 *
 * @extends AbstractBrowser<object, object>
 *
 * @author Simon André <smn.andre@gmail.com>
 */
class PlaywrightKernelClient extends AbstractBrowser
{
    private const FETCH_REDIRECT_SCRIPT = <<<'JS'
        (() => {
            const nativeFetch = window.fetch.bind(window);

            const withRedirectedFlag = (response, url) => new Proxy(response, {
                get(target, property) {
                    if (property === 'redirected') {
                        return true;
                    }

                    if (property === 'url') {
                        return url;
                    }

                    const value = Reflect.get(target, property, target);

                    return typeof value === 'function' ? value.bind(target) : value;
                },
            });

            window.fetch = async (input, init) => {
                let request = new Request(input, init);

                for (let redirects = 0; redirects <= 10; ++redirects) {
                    const preservedRequest = request.clone();
                    const response = await nativeFetch(request);
                    const location = response.headers.get('X-Playwright-PHP-Redirect');

                    if (!location) {
                        return redirects === 0 ? response : withRedirectedFlag(response, request.url);
                    }

                    if (redirects === 10) {
                        throw new TypeError('Maximum redirect count exceeded');
                    }

                    const status = Number(response.headers.get('X-Playwright-PHP-Redirect-Status'));
                    const changeToGet = (status === 303 && preservedRequest.method !== 'GET' && preservedRequest.method !== 'HEAD')
                        || ((status === 301 || status === 302) && preservedRequest.method === 'POST');
                    const headers = new Headers(preservedRequest.headers);
                    const method = changeToGet ? 'GET' : preservedRequest.method;

                    if (changeToGet) {
                        headers.delete('Content-Length');
                        headers.delete('Content-Type');
                    }

                    request = new Request(location, {
                        body: method === 'GET' || method === 'HEAD' ? undefined : await preservedRequest.blob(),
                        cache: preservedRequest.cache,
                        credentials: preservedRequest.credentials,
                        headers,
                        keepalive: preservedRequest.keepalive,
                        method,
                        mode: preservedRequest.mode,
                        redirect: preservedRequest.redirect,
                        referrer: preservedRequest.referrer,
                        referrerPolicy: preservedRequest.referrerPolicy,
                        signal: preservedRequest.signal,
                    });
                }
            };
        })();
        JS;

    private ?SymfonyRequest $lastSymfonyRequest = null;
    private ?SymfonyResponse $lastSymfonyResponse = null;
    /** @var string[] */
    private array $interceptedHosts = ['localhost', '127.0.0.1', 'testapp.local'];
    private ?object $hookReceiver = null;
    private bool $interceptorSetUp = false;
    private ?AssetServer $assetServer;
    private ?string $lastProfileToken = null;
    private bool $profileNextRequest = false;
    private ?bool $profilerWasEnabled = null;
    private bool $catchExceptions = true;
    private readonly BrowserSessionInterface $session;

    /**
     * @param array<string, mixed> $server
     * @param string[]|null        $interceptedHosts
     */
    public function __construct(
        BrowserSessionInterface $browser,
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
        $context = $browser->getContext();

        parent::__construct($server, cookieJar: $context ? new CookieJar($context, static fn (): string => parse_url($baseUrl, \PHP_URL_HOST) ?: 'localhost') : null);

        $this->session = $browser;

        if (null !== $interceptedHosts) {
            $this->interceptedHosts = $interceptedHosts;
        }

        $this->hookReceiver = $hookReceiver;
        $this->assetServer = $assetServer;
        $this->logger = $logger ?? new NullLogger();

        $context?->addInitScript(self::FETCH_REDIRECT_SCRIPT);
    }

    public function catchExceptions(bool $catchExceptions): void
    {
        $this->catchExceptions = $catchExceptions;
    }

    public function visit(string $path): PageInterface
    {
        $this->ensureInterceptorSetUp();
        $url = $this->getBaseUrl().$path;
        $this->log('debug', 'Navigating with Playwright', ['url' => $url]);
        $page = $this->session->getPage();

        if (null === $page) {
            throw new \RuntimeException('No page available. Browser may not be started.');
        }

        $page->goto($url);

        return $page;
    }

    public function getPage(): ?PageInterface
    {
        return $this->session->getPage();
    }

    public function context(): ?BrowserContextInterface
    {
        return $this->session->getContext();
    }

    /**
     * @param array<string, mixed> $serverParameters
     */
    public function click(Link $link, array $serverParameters = []): Crawler
    {
        $this->ensureInterceptorSetUp();
        $xpath = XPathHelper::buildXPath($link->getNode());
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

        FormInteractor::fill($page, $form);

        // Submit via JS to trigger Playwright's network interception
        $xpath = XPathHelper::buildXPath($form->getNode());
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
        $context = $this->session->getContext();

        if (null === $context) {
            throw new \RuntimeException('Browser context is null - browser may not be started');
        }

        $context->addCookies([$cookie]);
    }

    public function getCookie(string $name, ?string $url = null): ?string
    {
        $url ??= $this->getBaseUrl();
        $cookies = $this->session->getContext()?->cookies([$url]) ?? [];

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
        $this->session->getContext()?->clearCookies();
        $this->getCookieJar()->clear();
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
        $this->getCookieJar()->expire($name, $path, $domain);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function authenticate(string $identifier = 'user', array $context = []): void
    {
        $payload = json_encode(['id' => $identifier, 'ctx' => $context], JSON_THROW_ON_ERROR);
        $this->setCookie('AUTH', $payload);
    }

    /**
     * @param array<string, mixed> $tokenAttributes
     *
     * @return $this
     */
    public function loginUser(object $user, string $firewallContext = 'main', array $tokenAttributes = []): static
    {
        $container = $this->getContainer();
        if (null === $container) {
            throw new \LogicException(sprintf('"%s" requires a Symfony KernelInterface instance.', __METHOD__));
        }

        if (!$container->has('security.untracked_token_storage')) {
            throw new \LogicException('The SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');
        }

        if (!$user instanceof UserInterface) {
            throw new \LogicException(sprintf('The first argument of "%s" must be an instance of "%s", "%s" provided.', __METHOD__, UserInterface::class, get_debug_type($user)));
        }

        $token = new TestBrowserToken($user->getRoles(), $user, $firewallContext);
        $token->setAttributes($tokenAttributes);

        $tokenStorage = $container->get('security.untracked_token_storage');
        if (!$tokenStorage instanceof TokenStorageInterface) {
            throw new \LogicException(sprintf('The "security.untracked_token_storage" service must implement "%s".', TokenStorageInterface::class));
        }
        $tokenStorage->setToken($token);

        $session = $this->getSession();
        if (null === $session) {
            return $this;
        }

        try {
            $session->set('_security_'.$firewallContext, serialize($token));
        } catch (\Throwable $e) {
            throw new \LogicException(sprintf('Cannot store the security token in the session: the user object of class "%s" is not serializable.', $user::class), 0, $e);
        }

        $session->save();
        $this->setCookie($session->getName(), $session->getId(), ['httpOnly' => true]);

        return $this;
    }

    public function logout(string $firewallContext = 'main'): static
    {
        $this->clearCookie('AUTH');

        $container = $this->getContainer();
        if (null === $container) {
            return $this;
        }

        if ($container->has('security.untracked_token_storage')) {
            $tokenStorage = $container->get('security.untracked_token_storage');
            if ($tokenStorage instanceof TokenStorageInterface) {
                $tokenStorage->setToken(null);
            }
        }

        $session = $this->getSession();
        if (null === $session) {
            return $this;
        }

        $session->remove('_security_'.$firewallContext);
        $session->save();
        $this->clearCookie($session->getName());

        return $this;
    }

    public function getSession(): ?SessionInterface
    {
        $container = $this->getContainer();
        if (null === $container) {
            return null;
        }

        if (!$container->has('session.factory')) {
            return null;
        }

        $sessionFactory = $container->get('session.factory');
        if (!$sessionFactory instanceof SessionFactoryInterface) {
            throw new \LogicException(sprintf('The "session.factory" service must implement "%s".', SessionFactoryInterface::class));
        }

        $session = $sessionFactory->createSession();

        if (null !== $sessionId = $this->getCookie($session->getName())) {
            $session->setId($sessionId);
        }

        $session->start();

        return $session;
    }

    public function getLastSymfonyRequest(): ?SymfonyRequest
    {
        return $this->lastSymfonyRequest;
    }

    public function getRequest(): object
    {
        return $this->lastSymfonyRequest ?? parent::getRequest();
    }

    public function getLastSymfonyResponse(): ?SymfonyResponse
    {
        return $this->lastSymfonyResponse;
    }

    /**
     * The test container is preferred when available: it exposes private services, which is what
     * tests need. Mirrors Symfony's KernelBrowser::getContainer().
     *
     * Null when the kernel does not expose a container (it is only typed as HttpKernelInterface).
     */
    public function getContainer(): ?ContainerInterface
    {
        if (!$this->kernel instanceof KernelInterface) {
            return null;
        }

        $container = $this->kernel->getContainer();

        if (!$container->has('test.service_container')) {
            return $container;
        }

        $testContainer = $container->get('test.service_container');

        return $testContainer instanceof ContainerInterface ? $testContainer : $container;
    }

    /**
     * Enable the profiler for the next request, mirroring KernelBrowser::enableProfiler().
     */
    public function enableProfiler(): void
    {
        $this->profileNextRequest = true;
    }

    public function getResponse(): object
    {
        return $this->lastSymfonyResponse ?? parent::getResponse();
    }

    public function getProfile(): ?Profile
    {
        if (null === $this->lastProfileToken) {
            return null;
        }

        return $this->profiler()?->loadProfile($this->lastProfileToken);
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
        $this->session->setupRouting(function (mixed $route): void {
            if (!is_object($route) || !method_exists($route, 'request')) {
                return;
            }
            $request = $route->request();
            \assert($request instanceof RequestInterface);
            $url = parse_url($request->url());

            if (!$this->shouldInterceptRequest($url)) {
                $this->log('debug', 'Continuing external request', [
                    'url' => $request->url(),
                    'method' => $request->method(),
                ]);
                if (method_exists($route, 'continue')) {
                    $route->continue();
                }

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
                    if (method_exists($route, 'fulfill')) {
                        $route->fulfill($assetResponse);
                    }

                    return;
                }

                $this->log('debug', 'AssetServer miss falling back to kernel', [
                    'url' => $request->url(),
                    'method' => $request->method(),
                ]);
            }

            $response = $this->handleInternalRequest($request);
            $location = $response->headers->get('location');
            $statusCode = $response->getStatusCode();
            if (
                'document' === $request->resourceType()
                && (
                    in_array($statusCode, [301, 302, 303], true)
                    || ('GET' === $request->method() && in_array($statusCode, [307, 308], true))
                )
                && null !== $location
                && '' !== $location
            ) {
                // Route::redirectNavigationRequest() is @internal in playwright-php/playwright.
                // Depending on it is deliberate, so its absence must fail loudly: falling back to
                // a plain fulfill() would leave the page on the 3xx with no error of any kind.
                if (!method_exists($route, 'redirectNavigationRequest')) {
                    throw new \RuntimeException(sprintf('Cannot follow the %d redirect to "%s": %s::redirectNavigationRequest() is missing. Upgrade playwright-php/playwright to 1.4.0 or later.', $statusCode, $location, get_debug_type($route)));
                }

                $fulfillOptions = $this->responseConverter->prepareFulfillOptions($response);
                $headers = is_array($fulfillOptions['headers'] ?? null) ? $fulfillOptions['headers'] : [];
                unset($headers['location'], $headers['Location']);
                $route->redirectNavigationRequest(
                    UriResolver::resolve($location, $request->url()),
                    ['headers' => $headers],
                );

                return;
            }

            if (
                'fetch' === $request->resourceType()
                && in_array($statusCode, [301, 302, 303, 307, 308], true)
                && null !== $location
                && '' !== $location
                && method_exists($route, 'fulfill')
            ) {
                $fulfillOptions = $this->responseConverter->prepareFulfillOptions($response);
                $headers = is_array($fulfillOptions['headers'] ?? null) ? $fulfillOptions['headers'] : [];
                unset($headers['location'], $headers['Location']);
                $headers['X-Playwright-PHP-Redirect'] = UriResolver::resolve($location, $request->url());
                $headers['X-Playwright-PHP-Redirect-Status'] = (string) $statusCode;

                $route->fulfill([
                    'status' => 200,
                    'headers' => $headers,
                    'body' => '',
                ]);

                return;
            }

            if (method_exists($route, 'fulfill')) {
                $route->fulfill($this->responseConverter->prepareFulfillOptions($response));
            }
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
            $this->startProfiling();
            $response = $this->kernel->handle($symfonyRequest, HttpKernelInterface::MAIN_REQUEST, $this->catchExceptions);
            $this->lastSymfonyResponse = $response;

            if ($this->kernel instanceof TerminableInterface) {
                $this->kernel->terminate($symfonyRequest, $response);
            }

            // only after terminate: that is where the profile is written to storage
            $this->stopProfiling();

            // always reassign: a request that was not profiled must not report the previous profile
            $this->lastProfileToken = $response->headers->get('X-Debug-Token');

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
            $this->stopProfiling();

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

    private function startProfiling(): void
    {
        if (!$this->profileNextRequest) {
            return;
        }

        $this->profileNextRequest = false;

        if (!$profiler = $this->profiler()) {
            return;
        }

        $this->profilerWasEnabled = $profiler->isEnabled();
        $profiler->enable();
    }

    /**
     * Restores whatever state the profiler was in before the request, so an application that
     * collects globally is not silently switched off.
     */
    private function stopProfiling(): void
    {
        if (null === $this->profilerWasEnabled) {
            return;
        }

        $wasEnabled = $this->profilerWasEnabled;
        $this->profilerWasEnabled = null;

        if (!$wasEnabled) {
            $this->profiler()?->disable();
        }
    }

    private function profiler(): ?Profiler
    {
        $container = $this->getContainer();

        if (!$container?->has('profiler')) {
            return null;
        }

        $profiler = $container->get('profiler');

        return $profiler instanceof Profiler ? $profiler : null;
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

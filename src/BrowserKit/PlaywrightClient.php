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

namespace Playwright\Symfony\BrowserKit;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Exception\TimeoutException;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Util\CookieJarSync;
use Playwright\Symfony\Util\FormInteractor;
use Playwright\Symfony\Util\XPathHelper;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\Link;

/**
 * Public BrowserKit client for real browser testing with Playwright (no kernel routing).
 *
 * This is the standalone, public-facing client for using Playwright with Symfony's BrowserKit API.
 * It performs real browser navigation via actual HTTP requests - there is NO request interception
 * and NO routing through Symfony's HttpKernel.
 *
 * Primary role in architecture:
 * - Public API for users who want BrowserKit compatibility with real Playwright browsers
 * - Can be autowired from the container (registered by PlaywrightBundle)
 * - Works with any website (internal or external) via real HTTP
 * - Provides DomCrawler snapshots of the live DOM after each action
 *
 * Key differences from PlaywrightKernelClient:
 * - Uses REAL HTTP requests (not in-process kernel routing)
 * - Can test external websites and APIs
 * - NO access to Symfony internals (no request/response inspection, no profiler)
 * - Cookies synced between Playwright context and BrowserKit's CookieJar
 *
 * How it works:
 * 1. User calls request('GET', 'https://example.com')
 * 2. Playwright navigates via page->goto() → real HTTP request
 * 3. Page loads with full browser behavior (JS, CSS, AJAX)
 * 4. Client builds DomCrawler from page->content()
 * 5. Returns Crawler for BrowserKit-compatible assertions
 *
 * Features:
 * - Real browser semantics: click(), submit() use Playwright actions (not synthetic HTTP)
 * - Popup support: opt-in via setFollowPopups(true), switches to new tabs when links open them
 * - Form handling: creates synthetic forms in-page to preserve browser events
 * - Cookie sync: keeps BrowserKit CookieJar in sync with Playwright context
 * - Server params: maps HTTP_* headers and PHP_AUTH_* credentials to Playwright
 *
 * When to use:
 * - Testing external websites or APIs
 * - Need real HTTP networking behavior
 * - Want BrowserKit API with real browser (alternative to Symfony Panther)
 * - Testing JavaScript-heavy applications
 *
 * When NOT to use:
 * - Testing your Symfony app with access to internals → use PlaywrightTestCase instead
 * - Need to inspect Symfony request/response → use PlaywrightKernelClient via PlaywrightTestCase
 *
 * Usage:
 * ```php
 * // Via dependency injection
 * $client = $container->get(PlaywrightClient::class);
 *
 * // Or manual instantiation
 * $context = Playwright::chromium();
 * $client = PlaywrightClient::fromContext($context);
 *
 * // BrowserKit API
 * $crawler = $client->request('GET', 'https://example.com');
 * $link = $crawler->selectLink('Documentation')->link();
 * $crawler = $client->click($link);
 * ```
 *
 * @extends AbstractBrowser<Request, BrowserKitResponse>
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class PlaywrightClient extends AbstractBrowser
{
    private BrowserContextInterface $context;

    private PageInterface $page;

    private ?BrowserKitResponse $lastResponse = null;

    private bool $followPopups = false;

    /**
     * @param array<string, mixed> $server
     */
    public function __construct(
        BrowserContextInterface $context,
        PageInterface $page,
        array $server = [],
        ?History $history = null,
        ?CookieJar $cookieJar = null,
    ) {
        parent::__construct($server, $history, $cookieJar);

        $this->context = $context;
        $this->page = $page;

        CookieJarSync::fromContext($this->cookieJar, $this->context);
    }

    /**
     * @param array<string, mixed> $server
     */
    public static function fromContext(
        BrowserContextInterface $context,
        array $server = [],
        ?History $history = null,
        ?CookieJar $cookieJar = null,
    ): self {
        return new self($context, $context->newPage(), $server, $history, $cookieJar);
    }

    public function getPage(): PageInterface
    {
        return $this->page;
    }

    /**
     * Enables or disables following popups opened by click() and submit().
     *
     * When enabled, an action that opens a new tab makes the client switch to
     * it (Panther-like). Every action then waits for a potential popup before
     * returning, which adds the popup timeout to actions that do not open one:
     * enable it only for interactions expected to open a new tab.
     */
    public function setFollowPopups(bool $followPopups): void
    {
        $this->followPopups = $followPopups;
    }

    public function getLastResponse(): ?BrowserKitResponse
    {
        return $this->lastResponse;
    }

    protected function doRequest($request): BrowserKitResponse
    {
        $method = strtoupper($request->getMethod());
        $uri = (string) $request->getUri();

        $this->applyServerParams($request);

        if (in_array($method, ['GET', 'HEAD'], true) && empty($request->getParameters()) && empty($request->getFiles())) {
            $response = $this->navigate($uri);
            $this->lastResponse = $response;

            return $response;
        }

        $response = $this->submitSyntheticForm(
            $uri,
            $method,
            $request->getParameters(), // @phpstan-ignore argument.type
            $request->getFiles(), // @phpstan-ignore argument.type
            (string) $request->getContent()
        );
        $this->lastResponse = $response;

        return $response;
    }

    /**
     * @param array<string, mixed> $serverParameters
     */
    public function click(Link $link, array $serverParameters = []): Crawler
    {
        $xpath = XPathHelper::buildXPath($link->getNode());
        $locator = $this->page->locator('xpath='.$xpath);
        $this->handlePotentialPopup(static function () use ($locator): void {
            $locator->click();
        });

        return $this->refreshSnapshotAndResponse();
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $serverParameters
     */
    public function submit(Form $form, array $values = [], array $serverParameters = []): Crawler
    {
        if (!empty($values)) {
            $form->setValues($values);
        }

        FormInteractor::fill($this->page, $form);

        $this->handlePotentialPopup(function () use ($form): void {
            $xpath = XPathHelper::buildXPath($form->getNode());
            $this->page->locator('xpath='.$xpath)->evaluate('el => el.requestSubmit ? el.requestSubmit() : el.submit()');
        });

        return $this->refreshSnapshotAndResponse();
    }

    private function navigate(string $url): BrowserKitResponse
    {
        $playwrightResponse = $this->page->goto($url, ['waitUntil' => 'load']);
        $content = $this->page->content() ?? '';
        $status = $playwrightResponse?->status() ?? 200;
        $headers = $playwrightResponse?->headers() ?? [];

        CookieJarSync::toJarFromUrl($this->cookieJar, $this->context, $this->page->url());

        return $this->createBrowserKitResponse($content, $status, $headers);
    }

    /**
     * Maps Playwright response data to BrowserKit Response.
     *
     * @param array<string, string|array<int, string>> $headers
     */
    private function createBrowserKitResponse(string $content, int $status, array $headers): BrowserKitResponse
    {
        $flatHeaders = [];
        foreach ($headers as $name => $value) {
            $flatHeaders[$name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return new BrowserKitResponse($content, $status, $flatHeaders);
    }

    /**
     * Submits a synthetic form in-page to preserve browser semantics (JS, events).
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $files
     */
    private function submitSyntheticForm(string $action, string $method, array $params, array $files, string $rawContent): BrowserKitResponse
    {
        $method = strtoupper($method);
        $this->page->evaluate(
            <<<'JS'
({url, method}) => {
  const form = document.createElement('form');
  form.action = url;
  form.method = method;
  form.style.display = 'none';
  form.id = '__pw_form';
  document.body.appendChild(form);
}
JS,
            ['url' => $action, 'method' => $method]
        );

        foreach ($params as $name => $value) {
            $this->page->evaluate(
                '({name, value}) => { const input = document.createElement("input"); input.name=name; input.value=String(value); document.getElementById("__pw_form").appendChild(input); }',
                ['name' => $name, 'value' => $value]
            );
        }

        foreach ($files as $name => $path) {
            $locator = $this->page->locator('input[type="file"][name="'.addslashes($name).'"]');
            if (0 === $locator->count()) {
                $this->page->evaluate(
                    '({name}) => { const f = document.createElement("input"); f.type="file"; f.name=name; f.style.display="none"; document.getElementById("__pw_form").appendChild(f); }',
                    ['name' => $name]
                );
            }
            // Ensure proper type for setInputFiles
            if (is_array($path)) {
                /** @var string[] $filePaths */
                $filePaths = array_values(array_filter(array_map(static function (mixed $v): string {
                    if (is_string($v)) {
                        return $v;
                    }
                    if (is_int($v)) {
                        return (string) $v;
                    }

                    return '';
                }, $path)));
            } else {
                /** @var string $path */
                $filePaths = (string) $path;
            }
            $this->page->locator('input[type="file"][name="'.addslashes($name).'"]')->setInputFiles($filePaths);
        }

        $this->handlePotentialPopup(fn (): mixed => $this->page->evaluate('() => { const form = document.getElementById("__pw_form"); return form.requestSubmit ? form.requestSubmit() : form.submit(); }'));

        $content = $this->page->content() ?? '';
        $status = 200;
        $headers = [];

        CookieJarSync::toJarFromUrl($this->cookieJar, $this->context, $this->page->url());

        return $this->createBrowserKitResponse($content, $status, $headers);
    }

    private function refreshSnapshotAndResponse(): Crawler
    {
        $content = $this->page->content() ?? '';
        $status = 200;
        $headers = [];

        CookieJarSync::toJarFromUrl($this->cookieJar, $this->context, $this->page->url());
        $this->lastResponse = $this->createBrowserKitResponse($content, $status, $headers);

        return new Crawler($content, $this->page->url());
    }

    private function handlePotentialPopup(callable $action): void
    {
        if (!$this->followPopups) {
            $action();

            return;
        }

        try {
            $this->page = $this->page->context()->waitForPopup(static function () use ($action): void {
                $action();
            });
        } catch (TimeoutException) {
            // The action ran but no popup opened: stay on the current page.
        }
    }

    private function applyServerParams(Request $request): void
    {
        $server = $request->getServer();
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
            }
        }
        if (!empty($headers)) {
            if (method_exists($this->context, 'setExtraHTTPHeaders')) {
                $this->context->setExtraHTTPHeaders($headers);
            }
        }

        if (isset($server['PHP_AUTH_USER'], $server['PHP_AUTH_PW'])) {
            if (method_exists($this->context, 'setHttpCredentials')) {
                $username = $server['PHP_AUTH_USER'];
                $password = $server['PHP_AUTH_PW'];
                $this->context->setHttpCredentials([
                    'username' => is_string($username) ? $username : (is_scalar($username) ? (string) $username : ''),
                    'password' => is_string($password) ? $password : (is_scalar($password) ? (string) $password : ''),
                ]);
            }
        }
    }
}

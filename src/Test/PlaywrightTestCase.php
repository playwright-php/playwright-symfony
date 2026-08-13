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

namespace Playwright\Symfony\Test;

use Playwright\Page\PageInterface;
use Playwright\Symfony\Client\BrowserRegistry;
use Playwright\Symfony\Client\BrowserSessionInterface;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Client\PlaywrightKernelClient;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Base test case for E2E testing Symfony applications with Playwright and in-process kernel routing.
 *
 * This class provides a complete testing environment that combines:
 * - Real Playwright browser (chromium/firefox/webkit) for authentic browser behavior
 * - Symfony HttpKernel integration for in-process request handling
 * - Access to Symfony internals (container, services, profiler, request/response)
 * - BrowserKit-compatible API for familiar testing patterns
 *
 * Architecture overview:
 * - Extends WebTestCase → uses Symfony's functional-test lifecycle and test container
 * - Lazily creates a shared BrowserRegistry → manages browser lifecycle across tests
 * - Creates PlaywrightKernelClient instances → intercepts requests and routes through kernel
 * - Reuses one browser process for isolated client contexts
 *
 * Key features:
 * - Browser sharing: One browser instance per test class (performance optimization)
 * - Context isolation: Client contexts are closed after each test
 * - Request interception: All requests to localhost/127.0.0.1 routed through kernel
 * - Asset optimization: Static assets served directly without kernel overhead
 * - Hook system: beforeRequest() and afterResponse() for custom logic
 *
 * How request flow works:
 * 1. Test calls visit('/login') or creates a Playwright client
 * 2. Browser navigates to http://localhost/login
 * 3. Request intercepted by PlaywrightKernelClient
 * 4. AssetServer checks if it's a static asset → serves directly if yes
 * 5. Otherwise: RequestConverter → HttpKernel->handle() → ResponseConverter
 * 6. Response fulfilled in browser → page renders with full JS/CSS
 * 7. Test can inspect: getLastRequest(), getLastResponse(), getProfile()
 *
 * Configuration:
 * - Reads from bundle parameters: playwright.intercepted_hosts, playwright.base_url
 * - Reads from environment: PLAYWRIGHT_BROWSER, PLAYWRIGHT_HEADLESS
 * - Can configure via kernel container parameters
 *
 * Common methods:
 * - visit(string $path): PageInterface → Navigate to path, returns Playwright page
 * - getPlaywrightClient() → primary PlaywrightKernelClient, created lazily
 * - createPlaywrightClient() → fresh client with an isolated context and page
 * - setCookie(), authenticate(), logout() → Helpers for auth testing
 * - getLastRequest(), getLastResponse() → Inspect intercepted Symfony objects
 * - beforeRequest(), afterResponse() → Override for custom hooks
 *
 * Example usage:
 * ```php
 * class LoginTest extends PlaywrightTestCase
 * {
 *     protected static function createKernel(array $options = []): KernelInterface
 *     {
 *         return new Kernel('test', false);
 *     }
 *
 *     public function testUserCanLogin(): void
 *     {
 *         $page = $this->visit('/login');
 *         $page->fill('#username', 'admin');
 *         $page->fill('#password', 'secret');
 *         $page->click('button[type="submit"]');
 *
 *         $this->assertPageContains('Welcome back');
 *         $response = $this->getLastResponse();
 *         $this->assertSame(200, $response->getStatusCode());
 *     }
 * }
 * ```
 *
 * Requirements:
 * - Playwright browsers installed via: vendor/bin/playwright-install --browsers
 *
 * @author Simon André <smn.andre@gmail.com>
 */
abstract class PlaywrightTestCase extends WebTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static ?BrowserRegistry $sharedBrowser = null;
    protected static ?PlaywrightKernelClient $playwrightClient = null;
    private static ?self $hookReceiver = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::$hookReceiver = $this;
    }

    protected function tearDown(): void
    {
        $this->restoreExceptionHandlers();
        self::$playwrightClient = null;
        self::$hookReceiver = null;
        self::$sharedBrowser?->resetSessions();

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (null !== self::$sharedBrowser) {
            self::$sharedBrowser->stop();
            self::$sharedBrowser = null;
        }
        self::$playwrightClient = null;
        self::$hookReceiver = null;

        parent::tearDownAfterClass();
    }

    /**
     * Returns the primary client for the current test, creating it on first use.
     *
     * The same client is reused until tearDown() closes the test's browser sessions.
     */
    protected static function getPlaywrightClient(): PlaywrightKernelClient
    {
        $client = self::$playwrightClient ??= static::createPlaywrightClient();
        self::getClient($client);

        return $client;
    }

    /**
     * Creates a client with an isolated browser context and page.
     *
     * Clients created during one test share its browser process and Symfony kernel.
     */
    protected static function createPlaywrightClient(): PlaywrightKernelClient
    {
        $browser = self::getSharedBrowser();

        return self::createKernelClient($browser->createSession());
    }

    private static function getSharedBrowser(): BrowserRegistry
    {
        $requestedBrowser = BrowserRegistry::fromEnvironment();
        if (null !== self::$sharedBrowser && !self::$sharedBrowser->equals($requestedBrowser)) {
            self::$sharedBrowser->stop();
            self::$sharedBrowser = null;
        }

        return self::$sharedBrowser ??= $requestedBrowser;
    }

    private static function createKernelClient(BrowserSessionInterface $session): PlaywrightKernelClient
    {
        if (null === self::$kernel) {
            static::bootKernel();
        }

        if (null === self::$kernel) {
            throw new \RuntimeException('Kernel must be booted before creating client');
        }

        $logger = self::resolveLogger();
        $debugLogging = self::resolveDebugLogging();

        if ($debugLogging) {
            $logger->info('Playwright browser session ready', [
                'browser' => self::$sharedBrowser?->getBrowserType(),
                'headless' => self::$sharedBrowser?->isHeadless(),
            ]);
        }

        $client = new PlaywrightKernelClient(
            $session,
            self::$kernel,
            new RequestConverter(),
            new ResponseConverter(),
            [],
            self::loadInterceptedHosts(),
            self::$hookReceiver,
            self::resolveAssetServer(),
            self::resolveBaseUrl(),
            $logger,
            $debugLogging,
        );
        self::getClient($client);

        return $client;
    }

    private function restoreExceptionHandlers(): void
    {
        try {
            // Symfony's ErrorHandler and PHPUnit can push multiple exception handlers onto the stack.
            // A single `restore_exception_handler()` call only removes the topmost one.
            // This loop ensures all handlers pushed during test execution are popped off,
            // preventing interference with subsequent tests or other Symfony components.
            $maxIterations = 10; // Safeguard to prevent infinite loops if an unexpected handler persists
            $iterations = 0;

            while ($iterations < $maxIterations) {
                // Push a no-op handler to get the previous one without triggering an error
                $previousHandler = set_exception_handler(static fn () => null);
                // Remove the no-op handler
                restore_exception_handler();

                if (null === $previousHandler) {
                    // No more custom handlers, only PHP's default is left
                    break;
                }

                // Remove the previously found custom handler
                restore_exception_handler();
                ++$iterations;
            }
        } catch (\Throwable $e) {
            // If exception handler restoration fails, continue with teardown.
            // This prevents test failures due to issues in handler cleanup itself.
        }
    }

    protected function visit(string $path): PageInterface
    {
        return static::getPlaywrightClient()->visit($path);
    }

    protected function getPage(): PageInterface
    {
        $page = static::getPlaywrightClient()->getPage();
        if (null === $page) {
            throw new \RuntimeException('No page available. Browser may not be started.');
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function setCookie(string $name, string $value, array $options = []): void
    {
        static::getPlaywrightClient()->setCookie($name, $value, $options);
    }

    protected function getCookie(string $name, ?string $url = null): ?string
    {
        return static::getPlaywrightClient()->getCookie($name, $url);
    }

    protected function clearCookies(): void
    {
        static::getPlaywrightClient()->clearCookies();
    }

    protected function clearCookie(string $name, ?string $domain = null, string $path = '/'): void
    {
        static::getPlaywrightClient()->clearCookie($name, $domain, $path);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function authenticate(string $identifier = 'user', array $context = []): void
    {
        static::getPlaywrightClient()->authenticate($identifier, $context);
    }

    /**
     * @param \Symfony\Component\Security\Core\User\UserInterface $user
     * @param array<string, mixed>                                $tokenAttributes
     *
     * @return $this
     */
    protected function loginUser(object $user, string $firewallContext = 'main', array $tokenAttributes = []): static
    {
        static::getPlaywrightClient()->loginUser($user, $firewallContext, $tokenAttributes);

        return $this;
    }

    protected function logout(string $firewallContext = 'main'): static
    {
        static::getPlaywrightClient()->logout($firewallContext);

        return $this;
    }

    protected function getLastRequest(): ?SymfonyRequest
    {
        return static::getPlaywrightClient()->getLastSymfonyRequest();
    }

    protected function getLastResponse(): ?SymfonyResponse
    {
        return static::getPlaywrightClient()->getLastSymfonyResponse();
    }

    /**
     * Returns the base URL configured for browser navigation.
     */
    public function getBaseUrl(): string
    {
        return static::getPlaywrightClient()->getBaseUrl();
    }

    /**
     * Runs before each intercepted request is passed to the Symfony kernel.
     */
    public function beforeRequest(SymfonyRequest $request): void
    {
        // Override to add custom logic before each request
    }

    /**
     * Runs after each intercepted response is returned by the Symfony kernel.
     */
    public function afterResponse(SymfonyResponse $response): void
    {
        // Override to add custom logic after each response
    }

    /**
     * @param array<mixed> $fixtures
     */
    protected function loadFixtures(array $fixtures): void
    {
        // Override to load fixtures
    }

    private static function getTestContainer(): ContainerInterface
    {
        if (null === self::$kernel) {
            throw new \RuntimeException('Kernel is not booted');
        }

        return self::$kernel->getContainer();
    }

    /**
     * Returns the "test.service_container" if available, otherwise the main container.
     */
    private static function getPreferredContainer(): ContainerInterface
    {
        $container = self::getTestContainer();

        if ($container->has('test.service_container')) {
            $testContainer = $container->get('test.service_container');
            if ($testContainer instanceof ContainerInterface) {
                return $testContainer;
            }
        }

        return $container;
    }

    private static function getContainerParam(string $name): mixed
    {
        $container = self::getPreferredContainer();

        return $container->hasParameter($name) ? $container->getParameter($name) : null;
    }

    private static function getContainerService(string $id): mixed
    {
        $container = self::getPreferredContainer();

        return $container->has($id) ? $container->get($id) : null;
    }

    private static function resolveLogger(): LoggerInterface
    {
        if (null === self::$kernel) {
            return new NullLogger();
        }

        foreach (['monolog.logger.playwright', 'logger'] as $serviceId) {
            $candidate = self::getContainerService($serviceId);
            if ($candidate instanceof LoggerInterface) {
                return $candidate;
            }
        }

        return new NullLogger();
    }

    private static function resolveDebugLogging(): bool
    {
        /** @var string|bool|null $env */
        $env = $_ENV['PLAYWRIGHT_VERBOSE'] ?? $_SERVER['PLAYWRIGHT_VERBOSE'] ?? getenv('PLAYWRIGHT_VERBOSE');
        if (false !== $env && '' !== $env) {
            return !in_array(strtolower((string) $env), ['0', 'false', 'off'], true);
        }

        if (null === self::$kernel) {
            return false;
        }

        $param = self::getContainerParam('playwright.debug_logging');

        return null !== $param && (bool) $param;
    }

    private static function resolveBaseUrl(): string
    {
        $default = 'http://localhost';
        if (null === self::$kernel) {
            return $default;
        }

        $param = self::getContainerParam('playwright.base_url');

        return is_string($param) ? $param : $default;
    }

    /**
     * @return string[]
     */
    private static function loadInterceptedHosts(): array
    {
        $defaultHosts = ['localhost', '127.0.0.1', 'testapp.local'];
        if (null === self::$kernel) {
            return $defaultHosts;
        }

        $hosts = self::getContainerParam('playwright.intercepted_hosts');

        if (is_array($hosts) && !empty($hosts)) {
            $stringHosts = array_filter($hosts, 'is_string');
            if (!empty($stringHosts)) {
                /* @var string[] $stringHosts */
                return array_values($stringHosts);
            }
        }

        return $defaultHosts;
    }

    private static function resolveAssetServer(): ?AssetServer
    {
        if (null === self::$kernel) {
            return null;
        }

        $service = self::getContainerService(AssetServer::class);

        return $service instanceof AssetServer ? $service : null;
    }
}

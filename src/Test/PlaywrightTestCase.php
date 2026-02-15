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
use Playwright\Symfony\Browser\PlaywrightBrowser;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Client\PlaywrightClient;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Simplified test case that provides seamless DX while using the new component architecture.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
abstract class PlaywrightTestCase extends KernelTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static ?PlaywrightBrowser $sharedBrowser = null;
    protected PlaywrightBrowser $browser;
    protected PlaywrightClient $client;
    protected string $baseUrl = 'http://localhost';
    protected LoggerInterface $playwrightLogger;
    protected bool $debugLogging = false;

    protected function setUp(): void
    {
        $this->playwrightLogger = new NullLogger();
        $this->debugLogging = false;

        if ('1' !== ($_ENV['PLAYWRIGHT_E2E'] ?? $_SERVER['PLAYWRIGHT_E2E'] ?? getenv('PLAYWRIGHT_E2E'))) {
            $this->markTestSkipped('Playwright E2E tests are disabled. Set PLAYWRIGHT_E2E=1 to enable.');
        }

        parent::setUp();
        self::bootKernel();

        $this->baseUrl = $this->resolveBaseUrl();
        $this->debugLogging = $this->resolveDebugLogging();
        $this->playwrightLogger = $this->resolveLogger();

        $requestedBrowser = PlaywrightBrowser::fromEnvironment();
        if (null !== self::$sharedBrowser && !self::$sharedBrowser->equals($requestedBrowser)) {
            self::$sharedBrowser->stop();
            self::$sharedBrowser = null;
        }

        if (null === self::$sharedBrowser) {
            self::$sharedBrowser = $requestedBrowser;
            self::$sharedBrowser->start();
        } else {
            self::$sharedBrowser->restartContext();
        }
        $this->browser = self::$sharedBrowser;

        if ($this->debugLogging) {
            $this->playwrightLogger->info('Playwright browser session ready', [
                'browser' => $this->browser->getBrowserType(),
                'headless' => $this->browser->isHeadless(),
            ]);
        }

        if (null === self::$kernel) {
            throw new \RuntimeException('Kernel must be booted before creating client');
        }

        $this->client = new PlaywrightClient(
            $this->browser,
            self::$kernel,
            new RequestConverter(),
            new ResponseConverter(),
            [],
            $this->loadInterceptedHosts(),
            $this,
            $this->resolveAssetServer(),
            $this->baseUrl,
            $this->playwrightLogger,
            $this->debugLogging,
        );
    }

    protected function tearDown(): void
    {
        $this->restoreExceptionHandlers();
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (null !== self::$sharedBrowser) {
            self::$sharedBrowser->stop();
            self::$sharedBrowser = null;
        }

        parent::tearDownAfterClass();
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
        return $this->client->visit($path);
    }

    protected function getPage(): PageInterface
    {
        $page = $this->client->getPage();
        if (null === $page) {
            throw new \RuntimeException('No page available. Browser may not be started.');
        }

        return $page;
    }

    public function __get(string $name): mixed
    {
        if ('page' === $name) {
            return $this->getPage();
        }

        throw new \InvalidArgumentException("Property '$name' does not exist");
    }

    public function __set(string $name, mixed $value): void
    {
        throw new \InvalidArgumentException("Property '$name' is read-only or does not exist");
    }

    public function __isset(string $name): bool
    {
        return 'page' === $name;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function setCookie(string $name, string $value, array $options = []): void
    {
        $this->client->setCookie($name, $value, $options);
    }

    protected function getCookie(string $name, ?string $url = null): ?string
    {
        return $this->client->getCookie($name, $url);
    }

    protected function clearCookies(): void
    {
        $this->client->clearCookies();
    }

    protected function clearCookie(string $name, ?string $domain = null, string $path = '/'): void
    {
        $this->client->clearCookie($name, $domain, $path);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function authenticate(string $identifier = 'user', array $context = []): void
    {
        $this->client->authenticate($identifier, $context);
    }

    protected function logout(): void
    {
        $this->client->logout();
    }

    protected function getLastRequest(): ?SymfonyRequest
    {
        return $this->client->getLastSymfonyRequest();
    }

    protected function getLastResponse(): ?SymfonyResponse
    {
        return $this->client->getLastSymfonyResponse();
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function beforeRequest(SymfonyRequest $request): void
    {
        // Override to add custom logic before each request
    }

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

    private function getTestContainer(): ContainerInterface
    {
        if (null === self::$kernel) {
            throw new \RuntimeException('Kernel is not booted');
        }

        return self::$kernel->getContainer();
    }

    /**
     * Returns the "test.service_container" if available, otherwise the main container.
     */
    private function getPreferredContainer(): ContainerInterface
    {
        $container = $this->getTestContainer();

        if ($container->has('test.service_container')) {
            $testContainer = $container->get('test.service_container');
            if ($testContainer instanceof ContainerInterface) {
                return $testContainer;
            }
        }

        return $container;
    }

    private function getContainerParam(string $name): mixed
    {
        $container = $this->getPreferredContainer();

        return $container->hasParameter($name) ? $container->getParameter($name) : null;
    }

    private function getContainerService(string $id): mixed
    {
        $container = $this->getPreferredContainer();

        return $container->has($id) ? $container->get($id) : null;
    }

    private function resolveLogger(): LoggerInterface
    {
        if (null === self::$kernel) {
            return new NullLogger();
        }

        foreach (['monolog.logger.playwright', 'logger'] as $serviceId) {
            $candidate = $this->getContainerService($serviceId);
            if ($candidate instanceof LoggerInterface) {
                return $candidate;
            }
        }

        return new NullLogger();
    }

    private function resolveDebugLogging(): bool
    {
        /** @var string|bool|null $env */
        $env = $_ENV['PLAYWRIGHT_VERBOSE'] ?? $_SERVER['PLAYWRIGHT_VERBOSE'] ?? getenv('PLAYWRIGHT_VERBOSE');
        if (false !== $env && '' !== $env) {
            return !in_array(strtolower((string) $env), ['0', 'false', 'off'], true);
        }

        if (null === self::$kernel) {
            return false;
        }

        $param = $this->getContainerParam('playwright.debug_logging');

        return null !== $param && (bool) $param;
    }

    private function resolveBaseUrl(): string
    {
        $default = 'http://localhost';
        if (null === self::$kernel) {
            return $default;
        }

        $param = $this->getContainerParam('playwright.base_url');

        return is_string($param) ? $param : $default;
    }

    /**
     * @return string[]
     */
    private function loadInterceptedHosts(): array
    {
        $defaultHosts = ['localhost', '127.0.0.1', 'testapp.local'];
        if (null === self::$kernel) {
            return $defaultHosts;
        }

        $hosts = $this->getContainerParam('playwright.intercepted_hosts');

        if (is_array($hosts) && !empty($hosts)) {
            $stringHosts = array_filter($hosts, 'is_string');
            if (!empty($stringHosts)) {
                /* @var string[] $stringHosts */
                return array_values($stringHosts);
            }
        }

        return $defaultHosts;
    }

    private function resolveAssetServer(): ?AssetServer
    {
        if (null === self::$kernel) {
            return null;
        }

        $service = $this->getContainerService(AssetServer::class);

        return $service instanceof AssetServer ? $service : null;
    }
}

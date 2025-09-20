<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Test;

use Playwright\Page\PageInterface;
use Playwright\Symfony\Browser\PlaywrightBrowser;
use Playwright\Symfony\Client\PlaywrightClient;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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

    protected PlaywrightBrowser $browser;
    protected PlaywrightClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->browser = PlaywrightBrowser::fromEnvironment();
        $this->browser->start();

        $interceptedHosts = $this->loadInterceptedHosts();

        $this->client = new PlaywrightClient(
            $this->browser,
            self::$kernel,
            new RequestConverter(),
            new ResponseConverter(),
            [],
            $interceptedHosts,
            $this
        );
    }

    protected function tearDown(): void
    {
        try {
            $this->browser->stop();
        } catch (\Throwable $e) {
            // Ignore browser stop errors during teardown
        }

        $this->restoreExceptionHandlers();
        parent::tearDown();
    }

    private function restoreExceptionHandlers(): void
    {
        try {
            // Restore all exception handlers that may have been set during test execution
            $maxIterations = 10; // Prevent infinite loops
            $iterations = 0;

            while ($iterations < $maxIterations) {
                $previousHandler = set_exception_handler(static fn () => null);
                restore_exception_handler();

                if (null === $previousHandler) {
                    break;
                }

                restore_exception_handler();
                ++$iterations;
            }
        } catch (\Throwable $e) {
            // If exception handler restoration fails, continue with teardown
            // This prevents test failures due to exception handler cleanup issues
        }
    }

    // Clean delegate methods for seamless DX

    protected function visit(string $path): PageInterface
    {
        return $this->client->visit($path);
    }

    protected function getPage(): PageInterface
    {
        return $this->client->getPage();
    }

    // Property accessor for the trait
    public function __get(string $name): mixed
    {
        if ('page' === $name) {
            return $this->getPage();
        }
        throw new \InvalidArgumentException("Property '$name' does not exist");
    }

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
        return 'http://localhost';
    }

    // Hooks for customization
    public function beforeRequest(SymfonyRequest $request): void
    {
        // Override to add custom logic before each request
    }

    public function afterResponse(SymfonyResponse $response): void
    {
        // Override to add custom logic after each response
    }

    protected function loadFixtures(array $fixtures): void
    {
        // Override to load fixtures
    }

    private function loadInterceptedHosts(): array
    {
        $container = self::$kernel->getContainer();
        $defaultHosts = ['localhost', '127.0.0.1', 'testapp.local'];

        if ($container->hasParameter('playwright.intercepted_hosts')) {
            $hosts = $container->getParameter('playwright.intercepted_hosts');

            return empty($hosts) ? $defaultHosts : $hosts;
        }

        return $defaultHosts;
    }
}

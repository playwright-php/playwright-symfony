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

namespace Playwright\Symfony\Tests\Fixtures\Tests;

use Playwright\Symfony\Browser\PlaywrightBrowser;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Client\PlaywrightClient;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class TestablePlaywrightTestCase extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class implements KernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
            {
                return new SymfonyResponse('test');
            }

            public function registerBundles(): iterable
            {
                return [];
            }

            public function registerContainerConfiguration($loader): void
            {
            }

            public function boot(): void
            {
            }

            public function shutdown(): void
            {
            }

            public function getBundles(): array
            {
                return [];
            }

            public function getBundle(string $name): object
            {
                return new \stdClass();
            }

            public function locateResource(string $name): string
            {
                return '';
            }

            public function getEnvironment(): string
            {
                return 'test';
            }

            public function isDebug(): bool
            {
                return true;
            }

            public function getProjectDir(): string
            {
                return '';
            }

            public function getContainer(): object
            {
                return new class {
                    public function hasParameter(string $name): bool
                    {
                        return false;
                    }

                    public function getParameter(string $name): mixed
                    {
                        return null;
                    }
                };
            }

            public function getStartTime(): float
            {
                return 0.0;
            }

            public function getCacheDir(): string
            {
                return '';
            }

            public function getBuildDir(): string
            {
                return '';
            }

            public function getLogDir(): string
            {
                return '';
            }

            public function getCharset(): string
            {
                return 'UTF-8';
            }

            public function terminate(Request $request, Response $response): void
            {
            }
        };
    }

    public function setTestClient(PlaywrightClient $client): void
    {
        $this->client = $client;
    }

    public function setTestBrowser(PlaywrightBrowser $browser): void
    {
        $this->browser = $browser;
    }

    public static function setSharedBrowser(?PlaywrightBrowser $browser): void
    {
        self::$sharedBrowser = $browser;
    }

    public function setTestLogger(LoggerInterface $logger): void
    {
        $this->playwrightLogger = $logger;
    }

    public function setDebugLoggingFlag(bool $debug): void
    {
        $this->debugLogging = $debug;
    }

    public function callTearDown(): void
    {
        $this->tearDown();
    }

    public function publicSetCookie(string $name, string $value, array $options = []): void
    {
        $this->setCookie($name, $value, $options);
    }

    public function publicGetCookie(string $name, ?string $url = null): ?string
    {
        return $this->getCookie($name, $url);
    }

    public function publicClearCookies(): void
    {
        $this->clearCookies();
    }

    public function publicClearCookie(string $name, ?string $domain = null, string $path = '/'): void
    {
        $this->clearCookie($name, $domain, $path);
    }

    public function publicAuthenticate(string $identifier = 'user', array $context = []): void
    {
        $this->authenticate($identifier, $context);
    }

    public function publicLogout(): void
    {
        $this->logout();
    }

    public function publicGetLastRequest(): ?SymfonyRequest
    {
        return $this->getLastRequest();
    }

    public function publicGetLastResponse(): ?SymfonyResponse
    {
        return $this->getLastResponse();
    }

    public function publicLoadFixtures(array $fixtures): void
    {
        $this->loadFixtures($fixtures);
    }

    public function publicBeforeRequest(SymfonyRequest $request): void
    {
        $this->beforeRequest($request);
    }

    public function publicAfterResponse(SymfonyResponse $response): void
    {
        $this->afterResponse($response);
    }

    public function publicResolveBaseUrl(): string
    {
        $closure = \Closure::bind(
            static fn (): string => $this->resolveBaseUrl(),
            $this,
            PlaywrightTestCase::class
        );

        return $closure();
    }

    public function publicResolveDebugLogging(): bool
    {
        $closure = \Closure::bind(
            static fn (): bool => $this->resolveDebugLogging(),
            $this,
            PlaywrightTestCase::class
        );

        return $closure();
    }

    /**
     * @return string[]
     */
    public function publicLoadInterceptedHosts(): array
    {
        $closure = \Closure::bind(
            /**
             * @return string[]
             */
            static fn (): array => $this->loadInterceptedHosts(),
            $this,
            PlaywrightTestCase::class
        );

        return $closure();
    }

    public function publicResolveAssetServer(): ?AssetServer
    {
        $closure = \Closure::bind(
            static fn (): ?AssetServer => $this->resolveAssetServer(),
            $this,
            PlaywrightTestCase::class
        );

        return $closure();
    }

    public function publicResolveLogger(): LoggerInterface
    {
        $closure = \Closure::bind(
            static fn (): LoggerInterface => $this->resolveLogger(),
            $this,
            PlaywrightTestCase::class
        );

        return $closure();
    }
}

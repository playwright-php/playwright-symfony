<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Fixtures\Tests;

use Playwright\Network\RequestInterface;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Network\RequestInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ConcretePlaywrightTestCase extends PlaywrightTestCase
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

    // Public wrappers for testing methods that are now in components
    public function publicConvertToSymfonyRequest(RequestInterface $request): SymfonyRequest
    {
        $converter = new RequestConverter();

        return $converter->convertToSymfonyRequest($request);
    }

    public function publicFormatHeaders(array $headers): array
    {
        $converter = new ResponseConverter();

        return $converter->formatHeaders($headers);
    }

    public function publicShouldInterceptRequest(array $url): bool
    {
        $interceptedHosts = ['localhost', '127.0.0.1', 'testapp.local'];

        return isset($url['host']) && in_array($url['host'], $interceptedHosts, true);
    }

    public function publicGetBaseUrl(): string
    {
        return $this->getBaseUrl();
    }

    public function publicIsHeadless(): bool
    {
        // If browser is not set up (in unit tests), check environment directly
        if (!isset($this->browser)) {
            return 'false' !== getenv('PLAYWRIGHT_HEADLESS');
        }

        return $this->browser->isHeadless();
    }

    public function publicIsBinaryContentType(?string $contentType = null): bool
    {
        $converter = new ResponseConverter();

        return $converter->isBinaryContentType($contentType);
    }

    public function publicPrepareFulfillOptions(SymfonyResponse $response): array
    {
        $converter = new ResponseConverter();

        return $converter->prepareFulfillOptions($response);
    }

    public function setInterceptedHosts(array $hosts): void
    {
        if (isset($this->client)) {
            $this->client->setInterceptedHosts($hosts);
        }
        // For tests that don't set up the full client, just ignore this call
    }
}

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

namespace Playwright\Symfony\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Browser\PlaywrightBrowser;
use Playwright\Symfony\Client\Interception\AssetFile;
use Playwright\Symfony\Client\Interception\AssetLocatorInterface;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Client\PlaywrightClient;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Tests\Client\Fixtures\FakeBrowserContext;
use Playwright\Symfony\Tests\Client\Fixtures\FakeLogger;
use Playwright\Symfony\Tests\Client\Fixtures\FakePage;
use Playwright\Symfony\Tests\Client\Fixtures\TestPlaywrightBrowser;
use Playwright\Symfony\Tests\Fixtures\MockRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @uses \Playwright\Symfony\Util\CookieJarSync
 * @uses \Playwright\Symfony\Util\FormInteractor
 * @uses \Playwright\Symfony\Util\XPathHelper
 * @uses \Playwright\Symfony\Client\Interception\AssetServer
 * @uses \Playwright\Symfony\Client\Interception\AssetFile
 */
#[CoversClass(PlaywrightClient::class)]
#[CoversClass(PlaywrightBrowser::class)]
#[CoversClass(RequestConverter::class)]
#[CoversClass(ResponseConverter::class)]
#[CoversClass(AssetServer::class)]
#[CoversClass(AssetFile::class)]
class PlaywrightClientTest extends TestCase
{
    private TestPlaywrightBrowser $browser;
    private FakeBrowserContext $context;
    private FakePage $page;

    protected function setUp(): void
    {
        $this->context = new FakeBrowserContext();
        $this->page = new FakePage($this->context);
        $this->browser = new TestPlaywrightBrowser($this->context, $this->page);
    }

    public function testVisitNavigatesAndReturnsPage(): void
    {
        $client = new PlaywrightClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $page = $client->visit('/hello');

        self::assertSame($this->page, $page);
        self::assertSame('http://localhost/hello', $this->page->lastGoto);
    }

    public function testVisitRespectsCustomBaseUrl(): void
    {
        $client = new PlaywrightClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
            baseUrl: 'https://example.test'
        );

        $page = $client->visit('/greet');

        self::assertSame($this->page, $page);
        self::assertSame('https://example.test/greet', $this->page->lastGoto);
        self::assertSame('https://example.test', $client->getBaseUrl());
    }

    public function testInterceptedRequestHandledThroughKernel(): void
    {
        $seen = (object) [
            'before' => null,
            'after' => null,
        ];

        $kernel = new class implements HttpKernelInterface {
            public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
            {
                return new SymfonyResponse('intercepted', 201, ['x-k' => 'v']);
            }
        };

        $hookReceiver = new class($seen) {
            public function __construct(private object $seen)
            {
            }

            public function beforeRequest(SymfonyRequest $request): void
            {
                $this->seen->before = $request;
            }

            public function afterResponse(SymfonyResponse $response): void
            {
                $this->seen->after = $response;
            }
        };

        $client = new PlaywrightClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
            [],
            ['localhost', '127.0.0.1', 'testapp.local'],
            $hookReceiver,
        );

        // Trigger the routing setup
        $client->visit('/anything');

        // Simulate a request from Playwright targeting an intercepted host
        $mock = new MockRequest(url: 'http://localhost/path?x=1', method: 'GET');
        $route = $this->page->triggerRequest($mock);

        self::assertTrue($route->fulfilled, 'Route should be fulfilled when intercepted');
        self::assertNotNull($route->fulfilledOptions);
        self::assertSame(201, $route->fulfilledOptions['status'] ?? null);

        // Hooks should have been called with appropriate objects
        self::assertInstanceOf(SymfonyRequest::class, $seen->before);
        self::assertInstanceOf(SymfonyResponse::class, $seen->after);
    }

    public function testNonInterceptedRequestContinues(): void
    {
        $client = new PlaywrightClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
            [],
            ['localhost', '127.0.0.1', 'testapp.local'],
        );

        $client->visit('/anything');

        // Simulate external host -> should continue
        $mock = new MockRequest(url: 'http://example.com/page', method: 'GET');
        $route = $this->page->triggerRequest($mock);

        self::assertTrue($route->continued);
        self::assertFalse($route->fulfilled);
    }

    public function testAssetRequestServedByAssetServer(): void
    {
        $kernel = new class implements HttpKernelInterface {
            public bool $handled = false;

            public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
            {
                $this->handled = true;

                return new SymfonyResponse('kernel');
            }
        };

        $asset = new AssetFile(null, null, null, 'text/css', 'body { color: red; }');
        $locator = new class($asset) implements AssetLocatorInterface {
            public function __construct(private AssetFile $asset)
            {
            }

            public function locate(string $requestPath): ?AssetFile
            {
                return str_contains($requestPath, '/assets/') ? $this->asset : null;
            }
        };

        $assetServer = new AssetServer([$locator], ['/assets'], true);

        $client = new PlaywrightClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
            [],
            ['localhost'],
            null,
            $assetServer,
        );

        $client->visit('/start');

        $mock = new MockRequest(url: 'http://localhost/assets/app.css', method: 'GET');
        $route = $this->page->triggerRequest($mock);

        self::assertTrue($route->fulfilled, 'Asset request should be fulfilled by asset server');
        self::assertSame('text/css', $route->fulfilledOptions['contentType'] ?? null);
        self::assertStringContainsString('color: red', base64_decode($route->fulfilledOptions['body'] ?? '', true) ?: ($route->fulfilledOptions['body'] ?? ''));
        self::assertFalse($kernel->handled, 'Kernel should not handle requests served by the asset server');
    }

    public function testLogsInterceptedRequestsWhenDebugEnabled(): void
    {
        $logger = new FakeLogger();

        $client = new PlaywrightClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('logged');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
            [],
            ['localhost'],
            null,
            null,
            'http://localhost',
            $logger,
            true,
        );

        $client->visit('/logged');
        $mock = new MockRequest(url: 'http://localhost/logged', method: 'GET');
        $this->page->triggerRequest($mock);

        self::assertTrue($logger->hasRecord('info', static function (array $context, string $message): bool {
            return ($context['uri'] ?? null) === 'http://localhost/logged'
                && ($context['status_code'] ?? null) === 200
                && 'Fulfilled intercepted request' === $message;
        }));
    }

    public function testDoesNotLogWhenDebugDisabled(): void
    {
        $logger = new FakeLogger();

        $client = new PlaywrightClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('logged');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
            [],
            ['localhost'],
            null,
            null,
            'http://localhost',
            $logger,
            false,
        );

        $client->visit('/logged');
        $mock = new MockRequest(url: 'http://localhost/logged', method: 'GET');
        $this->page->triggerRequest($mock);

        self::assertFalse($logger->hasRecord('info'));
    }

    public function testCookieHelpersWorkWithContext(): void
    {
        $client = new PlaywrightClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $client->setCookie('foo', 'bar');
        self::assertSame('bar', $client->getCookie('foo'));

        $client->clearCookie('foo');
        // Now returns null for empty value
        self::assertNull($client->getCookie('foo'));

        $client->clearCookies();
        self::assertNull($client->getCookie('foo'));
    }
}

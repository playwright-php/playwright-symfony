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
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Client\BrowserRegistry;
use Playwright\Symfony\Client\Interception\AssetFile;
use Playwright\Symfony\Client\Interception\AssetLocatorInterface;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Client\PlaywrightKernelClient;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Tests\Client\Fixtures\FakeBrowserContext;
use Playwright\Symfony\Tests\Client\Fixtures\FakeLogger;
use Playwright\Symfony\Tests\Client\Fixtures\FakePage;
use Playwright\Symfony\Tests\Client\Fixtures\TestBrowserRegistry;
use Playwright\Symfony\Tests\Fixtures\MockRequest;
use Playwright\Symfony\Util\CookieJarSync;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(PlaywrightKernelClient::class)]
#[CoversClass(BrowserRegistry::class)]
#[CoversClass(RequestConverter::class)]
#[CoversClass(ResponseConverter::class)]
#[CoversClass(AssetServer::class)]
#[CoversClass(AssetFile::class)]
#[UsesClass(CookieJarSync::class)]
class PlaywrightKernelClientTest extends TestCase
{
    private TestBrowserRegistry $browser;
    private FakeBrowserContext $context;
    private FakePage $page;

    protected function setUp(): void
    {
        $this->context = new FakeBrowserContext();
        $this->page = new FakePage($this->context);
        $this->browser = new TestBrowserRegistry($this->context, $this->page);
    }

    public function testVisitNavigatesAndReturnsPage(): void
    {
        $client = new PlaywrightKernelClient(
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
        $client = new PlaywrightKernelClient(
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

        $client = new PlaywrightKernelClient(
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

    public function testDocumentRedirectUsesPlaywrightNavigationRedirect(): void
    {
        $client = new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('', 302, [
                        'location' => '/target?from=redirect',
                        'set-cookie' => 'redirected=yes; Path=/',
                    ]);
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );
        $client->visit('/start');

        $route = $this->page->triggerRequest(new MockRequest(
            url: 'http://localhost/source',
            resourceType: 'document',
        ));

        self::assertSame('http://localhost/target?from=redirect', $route->redirectUrl);
        self::assertSame('redirected=yes; path=/', $route->redirectOptions['headers']['set-cookie'] ?? null);
        self::assertArrayNotHasKey('location', $route->redirectOptions['headers'] ?? []);
        self::assertFalse($route->fulfilled);
    }

    public function testMethodPreservingDocumentRedirectIsOnlyReplayedForGetRequests(): void
    {
        $client = new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('', 307, ['location' => '/target']);
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );
        $client->visit('/start');

        $route = $this->page->triggerRequest(new MockRequest(
            url: 'http://localhost/source',
            method: 'POST',
            resourceType: 'document',
        ));

        self::assertNull($route->redirectUrl);
        self::assertTrue($route->fulfilled);
        self::assertSame(307, $route->fulfilledOptions['status'] ?? null);
    }

    public function testFetchRedirectIsExposedToTheFetchWrapper(): void
    {
        $client = new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('', 303, ['location' => '../target']);
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );
        $client->visit('/start');

        $route = $this->page->triggerRequest(new MockRequest(
            url: 'http://localhost/path/source',
            method: 'POST',
            resourceType: 'fetch',
        ));

        self::assertNull($route->redirectUrl);
        self::assertTrue($route->fulfilled);
        self::assertSame(200, $route->fulfilledOptions['status'] ?? null);
        self::assertSame('http://localhost/target', $route->fulfilledOptions['headers']['X-Playwright-PHP-Redirect'] ?? null);
        self::assertSame('303', $route->fulfilledOptions['headers']['X-Playwright-PHP-Redirect-Status'] ?? null);
        self::assertArrayNotHasKey('location', $route->fulfilledOptions['headers'] ?? []);
    }

    public function testFetchRedirectWrapperIsInstalledOnTheBrowserContext(): void
    {
        new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse();
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        self::assertCount(1, $this->context->initScripts);
        self::assertStringContainsString('X-Playwright-PHP-Redirect', $this->context->initScripts[0]);
        self::assertStringContainsString('redirects <= 10', $this->context->initScripts[0]);
    }

    public function testNonInterceptedRequestContinues(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client = new PlaywrightKernelClient(
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

        $client = new PlaywrightKernelClient(
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

        $client = new PlaywrightKernelClient(
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
        $client = new PlaywrightKernelClient(
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

    public function testGetPageReturnsPageFromBrowser(): void
    {
        $client = new PlaywrightKernelClient(
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

        $page = $client->getPage();

        self::assertSame($this->page, $page);
    }

    public function testGetLastSymfonyRequestReturnsLastRequest(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->visit('/test');
        $mock = new MockRequest(url: 'http://localhost/test', method: 'GET');
        $this->page->triggerRequest($mock);

        $lastRequest = $client->getLastSymfonyRequest();

        self::assertNotNull($lastRequest);
        self::assertSame($lastRequest, $client->getRequest());
        self::assertInstanceOf(SymfonyRequest::class, $lastRequest);
        self::assertSame('/test', $lastRequest->getPathInfo());
    }

    public function testGetLastSymfonyResponseReturnsLastResponse(): void
    {
        $client = new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('test response content');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $client->visit('/test');
        $mock = new MockRequest(url: 'http://localhost/test', method: 'GET');
        $this->page->triggerRequest($mock);

        $lastResponse = $client->getLastSymfonyResponse();

        self::assertNotNull($lastResponse);
        self::assertSame($lastResponse, $client->getResponse());
        self::assertInstanceOf(SymfonyResponse::class, $lastResponse);
        self::assertSame('test response content', $lastResponse->getContent());
    }

    public function testSetInterceptedHostsUpdatesHostList(): void
    {
        $client = new PlaywrightKernelClient(
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
            ['localhost'],
        );

        $client->setInterceptedHosts(['example.com', 'test.local']);

        self::assertSame(['example.com', 'test.local'], $client->getInterceptedHosts());
    }

    public function testGetInterceptedHostsReturnsConfiguredHosts(): void
    {
        $client = new PlaywrightKernelClient(
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
            ['localhost', '127.0.0.1', 'custom.local'],
        );

        $hosts = $client->getInterceptedHosts();

        self::assertSame(['localhost', '127.0.0.1', 'custom.local'], $hosts);
    }

    public function testAuthenticateSetsAuthCookie(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->authenticate('admin', ['role' => 'ADMIN']);

        // Verify AUTH cookie was set
        $authCookie = $client->getCookie('AUTH');
        self::assertNotNull($authCookie);

        $payload = json_decode($authCookie, true);
        self::assertSame('admin', $payload['id']);
        self::assertSame(['role' => 'ADMIN'], $payload['ctx']);
    }

    public function testLogoutClearsAuthCookie(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->authenticate('user');
        self::assertNotNull($client->getCookie('AUTH'));

        $client->logout();
        self::assertNull($client->getCookie('AUTH'));
    }

    public function testLogoutClearsTheSecuritySessionAndItsCookie(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $tokenStorage = new TokenStorage();

        $container = new Container();
        $container->set('session.factory', new class($session) implements SessionFactoryInterface {
            public function __construct(private readonly SessionInterface $session)
            {
            }

            public function createSession(): SessionInterface
            {
                return $this->session;
            }
        });
        $container->set('security.untracked_token_storage', $tokenStorage);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );

        $session->start();
        $session->set('_security_main', 'a-serialized-token');
        $session->save();
        $client->setCookie($session->getName(), $session->getId());
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('u', null), 'main'));

        $client->logout();

        self::assertNull($session->get('_security_main'));
        self::assertNull($tokenStorage->getToken());
        self::assertNull($client->getCookie($session->getName()));
    }

    public function testLogoutTargetsTheGivenFirewallContext(): void
    {
        $session = new Session(new MockArraySessionStorage());

        $container = new Container();
        $container->set('session.factory', new class($session) implements SessionFactoryInterface {
            public function __construct(private readonly SessionInterface $session)
            {
            }

            public function createSession(): SessionInterface
            {
                return $this->session;
            }
        });

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );

        $session->start();
        $session->set('_security_admin', 'admin-token');
        $session->set('_security_main', 'main-token');
        $session->save();

        $client->logout('admin');

        self::assertNull($session->get('_security_admin'));
        self::assertSame('main-token', $session->get('_security_main'));
    }

    public function testLogoutOnlyClearsCookiesWhenSessionsAreUnavailable(): void
    {
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn(new Container());

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );

        $client->authenticate('user');

        self::assertSame($client, $client->logout());
        self::assertNull($client->getCookie('AUTH'));
    }

    public function testLoginUserStoresTheSymfonySecurityTokenAndSessionCookie(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $tokenStorage = new TokenStorage();

        $container = new Container();
        $container->set('session.factory', new class($session) implements SessionFactoryInterface {
            public function __construct(private readonly SessionInterface $session)
            {
            }

            public function createSession(): SessionInterface
            {
                return $this->session;
            }
        });
        $container->set('security.untracked_token_storage', $tokenStorage);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );
        $user = new InMemoryUser('admin@example.test', null, ['ROLE_ADMIN']);

        $returned = $client->loginUser($user, 'admin', ['source' => 'unit-test']);

        self::assertSame($client, $returned);

        $token = $tokenStorage->getToken();
        self::assertInstanceOf(TestBrowserToken::class, $token);
        self::assertSame($user, $token->getUser());
        self::assertSame('admin', $token->getFirewallName());
        self::assertSame(['source' => 'unit-test'], $token->getAttributes());

        $storedToken = unserialize((string) $session->get('_security_admin'));
        self::assertEquals($token, $storedToken);
        self::assertSame($session->getId(), $client->getCookie($session->getName()));
    }

    public function testLoginUserWithoutSessionMatchesSymfonyKernelBrowserBehavior(): void
    {
        $tokenStorage = new TokenStorage();
        $container = new Container();
        $container->set('security.untracked_token_storage', $tokenStorage);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );
        $user = new InMemoryUser('stateless@example.test', null, ['ROLE_USER']);

        $returned = $client->loginUser($user);

        self::assertSame($client, $returned);
        self::assertSame($user, $tokenStorage->getToken()?->getUser());
        self::assertNull($client->getCookie('MOCKSESSID'));
    }

    public function testLoginUserRejectsObjectsThatAreNotSymfonyUsers(): void
    {
        $container = new Container();
        $container->set('security.untracked_token_storage', new TokenStorage());

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must be an instance of "Symfony\\Component\\Security\\Core\\User\\UserInterface", "stdClass" provided');

        $client->loginUser(new \stdClass());
    }

    public function testLoginUserRequiresSecurityBundleToBeEnabled(): void
    {
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn(new Container());

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');

        $client->loginUser(new InMemoryUser('user@example.test', null));
    }

    public function testLoginUserRejectsInvalidTokenStorageService(): void
    {
        $container = new Container();
        $container->set('security.untracked_token_storage', new \stdClass());

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must implement "Symfony\\Component\\Security\\Core\\Authentication\\Token\\Storage\\TokenStorageInterface"');

        $client->loginUser(new InMemoryUser('user@example.test', null));
    }

    public function testLoginUserUsesTheTestServiceContainer(): void
    {
        $tokenStorage = new TokenStorage();
        $testContainer = new Container();
        $testContainer->set('security.untracked_token_storage', $tokenStorage);

        $container = new Container();
        $container->set('test.service_container', $testContainer);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );
        $user = new InMemoryUser('user@example.test', null);

        self::assertSame($client, $client->loginUser($user));
        self::assertSame($user, $tokenStorage->getToken()?->getUser());
    }

    public function testLoginUserRequiresAKernelInterface(): void
    {
        $client = new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse();
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('requires a Symfony KernelInterface instance');

        $client->loginUser(new InMemoryUser('user@example.test', null));
    }

    public function testGetSessionRejectsInvalidSessionFactoryService(): void
    {
        $container = new Container();
        $container->set('session.factory', new \stdClass());

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must implement "Symfony\\Component\\HttpFoundation\\Session\\SessionFactoryInterface"');

        $client->getSession();
    }

    public function testLoginUserReportsAnUnserializableUser(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $container = new Container();
        $container->set('session.factory', new class($session) implements SessionFactoryInterface {
            public function __construct(private readonly SessionInterface $session)
            {
            }

            public function createSession(): SessionInterface
            {
                return $this->session;
            }
        });
        $container->set('security.untracked_token_storage', new TokenStorage());

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getContainer')->willReturn($container);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
        );
        $user = new class implements UserInterface {
            private \Closure $callback;

            public function __construct()
            {
                $this->callback = static fn (): null => null;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function getUserIdentifier(): string
            {
                return 'unserializable@example.test';
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot store the security token in the session');

        $client->loginUser($user);
    }

    public function testLogoutIsFluent(): void
    {
        $client = new PlaywrightKernelClient(
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

        self::assertSame($client, $client->logout());
    }

    public function testGetProfileReturnsNullWhenNoProfileToken(): void
    {
        $client = new PlaywrightKernelClient(
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

        $profile = $client->getProfile();

        self::assertNull($profile);
    }

    public function testClearCookieWithCustomDomainAndPath(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->setCookie('test', 'value');
        $client->clearCookie('test', 'localhost', '/custom');

        // Cookie should be cleared
        self::assertNull($client->getCookie('test'));
    }

    public function testGetCookieWithCustomUrl(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->setCookie('session', 'abc123');
        $value = $client->getCookie('session', 'http://localhost');

        self::assertSame('abc123', $value);
    }

    public function testCapturesProfileTokenFromResponse(): void
    {
        $client = new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    $response = new SymfonyResponse('ok');
                    $response->headers->set('X-Debug-Token', 'test-token-123');

                    return $response;
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $client->visit('/test');
        $mock = new MockRequest(url: 'http://localhost/test', method: 'GET');
        $this->page->triggerRequest($mock);

        // Profile token should be captured
        $profile = $client->getProfile();
        // Since we don't have profiler service, this will return null, but token was captured
        self::assertNull($profile);
    }

    public function testHandlesKernelExceptions(): void
    {
        $client = new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    throw new \RuntimeException('Kernel error');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
            [],
            ['localhost'],
            null,
            null,
            'http://localhost',
            new FakeLogger(),
            false,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kernel error');

        $client->visit('/test');
        $mock = new MockRequest(url: 'http://localhost/test', method: 'GET');
        $this->page->triggerRequest($mock);
    }

    public function testLogsExceptionsWhenKernelThrows(): void
    {
        $logger = new FakeLogger();

        $client = new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    throw new \RuntimeException('Test exception');
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

        try {
            $client->visit('/test');
            $mock = new MockRequest(url: 'http://localhost/test', method: 'GET');
            $this->page->triggerRequest($mock);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($logger->hasRecord('error'));
    }

    public function testDoesNotInterceptWhenUrlIsFalse(): void
    {
        $client = new PlaywrightKernelClient(
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
            ['localhost'],
        );

        $client->visit('/test');

        // Malformed URL request should continue (not intercept)
        $mock = new MockRequest(url: 'not-a-valid-url', method: 'GET');
        $route = $this->page->triggerRequest($mock);

        self::assertTrue($route->continued);
        self::assertFalse($route->fulfilled);
    }

    public function testHandlesMultipleOutputBufferLevels(): void
    {
        $client = new PlaywrightKernelClient(
            $this->browser,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    // Create nested output buffers
                    ob_start();
                    echo 'level 1';
                    ob_start();
                    echo 'level 2';

                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $client->visit('/test');
        $mock = new MockRequest(url: 'http://localhost/test', method: 'GET');
        $this->page->triggerRequest($mock);

        $response = $client->getLastSymfonyResponse();

        self::assertNotNull($response);
        self::assertSame('ok', $response->getContent());
    }

    public function testBaseUrlCanBeCustomized(): void
    {
        $client = new PlaywrightKernelClient(
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
            ['custom.test'],
            null,
            null,
            'http://custom.test',
        );

        self::assertSame('http://custom.test', $client->getBaseUrl());
    }

    public function testVisitThrowsExceptionWhenPageIsNull(): void
    {
        $browserWithoutPage = new TestBrowserRegistry($this->context, null);

        $client = new PlaywrightKernelClient(
            $browserWithoutPage,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No page available. Browser may not be started.');

        $client->visit('/test');
    }

    public function testSetCookieThrowsExceptionWhenContextIsNull(): void
    {
        $browserWithoutContext = new TestBrowserRegistry(null, $this->page);

        $client = new PlaywrightKernelClient(
            $browserWithoutContext,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Browser context is null - browser may not be started');

        $client->setCookie('test', 'value');
    }

    public function testSetCookieHandlesIntExpires(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->setCookie('test', 'value', ['expires' => 1234567890]);

        $value = $client->getCookie('test');
        self::assertSame('value', $value);
    }

    public function testSetCookieHandlesNumericStringExpires(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->setCookie('test', 'value', ['expires' => '1234567890']);

        $value = $client->getCookie('test');
        self::assertSame('value', $value);
    }

    public function testSetCookieRemovesInvalidExpires(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->setCookie('test', 'value', ['expires' => 'invalid']);

        $value = $client->getCookie('test');
        self::assertSame('value', $value);
    }

    public function testGetCrawlerReturnsEmptyCrawlerWhenPageIsNull(): void
    {
        $browserWithoutPage = new TestBrowserRegistry($this->context, null);

        $client = new PlaywrightKernelClient(
            $browserWithoutPage,
            new class implements HttpKernelInterface {
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
                {
                    return new SymfonyResponse('ok');
                }
            },
            new RequestConverter(),
            new ResponseConverter(),
        );

        $crawler = $client->getCrawler();

        self::assertInstanceOf(Crawler::class, $crawler);
        self::assertCount(0, $crawler->filter('body > *'));
    }

    public function testAssetServerMissFallsBackToKernel(): void
    {
        $logger = new FakeLogger();
        $tracker = new class {
            public bool $kernelHandled = false;
        };

        $kernel = new class($tracker) implements HttpKernelInterface {
            public function __construct(private object $tracker)
            {
            }

            public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): SymfonyResponse
            {
                $this->tracker->kernelHandled = true;

                return new SymfonyResponse('kernel');
            }
        };

        $locator = new class implements AssetLocatorInterface {
            public function locate(string $requestPath): ?AssetFile
            {
                return null; // Always miss
            }
        };

        $assetServer = new AssetServer([$locator], ['/assets'], true);

        $client = new PlaywrightKernelClient(
            $this->browser,
            $kernel,
            new RequestConverter(),
            new ResponseConverter(),
            [],
            ['localhost'],
            null,
            $assetServer,
            'http://localhost',
            $logger,
            true,
        );

        $client->visit('/start');

        $mock = new MockRequest(url: 'http://localhost/assets/app.css', method: 'GET');
        $this->page->triggerRequest($mock);

        self::assertTrue($tracker->kernelHandled, 'Kernel should handle request when asset server misses');
        self::assertTrue($logger->hasRecord('debug', static function (array $context, string $message): bool {
            return 'AssetServer miss falling back to kernel' === $message;
        }));
    }

    public function testInvalidRouteObjectDoesNothing(): void
    {
        $logger = new FakeLogger();

        $client = new PlaywrightKernelClient(
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
            ['localhost'],
            null,
            null,
            'http://localhost',
            $logger,
            true,
        );

        $client->visit('/test');

        // Trigger with an invalid route object (no request method)
        $invalidRoute = new \stdClass();
        $this->page->triggerRequestWithInvalidRoute($invalidRoute);

        // Should have logged navigation but not handled invalid route
        self::assertTrue($logger->hasRecord('debug', static function (array $context, string $message): bool {
            return 'Navigating with Playwright' === $message;
        }));
    }

    public function testSetCookieHandlesCustomDomain(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->setCookie('test', 'value', ['domain' => 'custom.test']);

        $value = $client->getCookie('test');
        self::assertSame('value', $value);
    }

    public function testSetCookieHandlesCustomPath(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->setCookie('test', 'value', ['path' => '/admin']);

        $value = $client->getCookie('test');
        self::assertSame('value', $value);
    }

    public function testGetCookieReturnsNullForEmptyStringValue(): void
    {
        $client = new PlaywrightKernelClient(
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

        $client->setCookie('test', '');

        $value = $client->getCookie('test');
        self::assertNull($value);
    }
}

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

namespace Playwright\Symfony\Tests\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Network\Request;
use Playwright\Symfony\Asset\AssetMapperProxy;
use Playwright\Symfony\Asset\FilesystemProxy;
use Playwright\Symfony\Client\BrowserRegistry;
use Playwright\Symfony\Client\BrowserSession;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Client\PlaywrightKernelClient;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\Client\FakeBrowserRegistry;
use Playwright\Symfony\Tests\Fixtures\Client\FakePlaywrightKernelClient;
use Playwright\Symfony\Tests\Fixtures\MockRequest;
use Playwright\Symfony\Tests\Fixtures\Tests\ConcretePlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\Tests\TestablePlaywrightTestCase;
use Playwright\Symfony\Util\CookieJarSync;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(PlaywrightTestCase::class)]
#[CoversClass(RequestConverter::class)]
#[CoversClass(ResponseConverter::class)]
#[UsesClass(AssetMapperProxy::class)]
#[UsesClass(FilesystemProxy::class)]
#[UsesClass(BrowserRegistry::class)]
#[UsesClass(BrowserSession::class)]
#[UsesClass(AssetServer::class)]
#[UsesClass(PlaywrightKernelClient::class)]
#[UsesClass(CookieJarSync::class)]
class PlaywrightTestCaseTest extends TestCase
{
    private ConcretePlaywrightTestCase $testCase;
    private TestablePlaywrightTestCase $lifecycleTestCase;
    private FakePlaywrightKernelClient $client;
    private FakeBrowserRegistry $browser;

    protected function setUp(): void
    {
        $this->testCase = new ConcretePlaywrightTestCase('aa');
        $this->testCase->setInterceptedHosts(['localhost', '127.0.0.1', 'testapp.local']);

        $this->lifecycleTestCase = new TestablePlaywrightTestCase('dummy');
        $this->client = new FakePlaywrightKernelClient();
        $this->browser = new FakeBrowserRegistry();
        $this->lifecycleTestCase->setTestClient($this->client);
        $this->lifecycleTestCase->setTestBrowser($this->browser);
    }

    protected function tearDown(): void
    {
        TestablePlaywrightTestCase::tearDownAfterClass();
    }

    public function testExtendsWebTestCaseAndExposesSymfonyWebAssertions(): void
    {
        self::assertTrue(is_subclass_of(PlaywrightTestCase::class, WebTestCase::class));
        self::assertTrue(method_exists(PlaywrightTestCase::class, 'assertResponseIsSuccessful'));
        self::assertTrue(method_exists(PlaywrightTestCase::class, 'assertRouteSame'));
        self::assertTrue(method_exists(PlaywrightTestCase::class, 'assertSelectorExists'));
    }

    public function testConvertToSymfonyRequestHandlesGetRequest(): void
    {
        $playwrightRequest = new Request([
            'url' => 'http://localhost/test?foo=bar',
            'method' => 'GET',
            'headers' => ['content-type' => 'text/html'],
            'postData' => null,
        ]);

        $request = $this->testCase->publicConvertToSymfonyRequest($playwrightRequest);

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/test', $request->getPathInfo());
        $this->assertEquals('bar', $request->query->get('foo'));
        $this->assertEquals('text/html', $request->headers->get('content-type'));
    }

    public function testConvertToSymfonyRequestHandlesPostRequest(): void
    {
        $playwrightRequest = new MockRequest(
            url: 'http://localhost/submit',
            method: 'POST',
            headers: ['content-type' => 'application/x-www-form-urlencoded'],
            postData: 'name=John&email=john@example.com',
        );

        $request = $this->testCase->publicConvertToSymfonyRequest($playwrightRequest);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/submit', $request->getPathInfo());
        $this->assertEquals('name=John&email=john@example.com', $request->getContent());
    }

    public function testConvertToSymfonyRequestHandlesJsonPostData(): void
    {
        $postData = ['name' => 'John', 'email' => 'john@example.com'];
        $jsonData = json_encode($postData);

        $playwrightRequest = new MockRequest(
            url: 'http://localhost/api/users',
            method: 'POST',
            headers: ['content-type' => 'application/json'],
            postData: $jsonData,
        );

        $request = $this->testCase->publicConvertToSymfonyRequest($playwrightRequest);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/users', $request->getPathInfo());
        $this->assertEquals($jsonData, $request->getContent());
        // For JSON requests, the content should be in the body, not parsed into parameters
        $this->assertEmpty($request->request->all());
    }

    public function testConvertToSymfonyRequestHandlesFormUrlencodedArray(): void
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        $form = http_build_query($data); // name=John&email=john%40example.com

        $playwrightRequest = new MockRequest(
            url: 'http://localhost/api/users',
            method: 'POST',
            headers: ['content-type' => 'application/x-www-form-urlencoded'],
            postData: $form,
        );

        $request = $this->testCase->publicConvertToSymfonyRequest($playwrightRequest);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/users', $request->getPathInfo());
        $this->assertSame($form, $request->getContent());
    }

    public function testConvertToSymfonyRequestHandlesMultipartFormDataFields(): void
    {
        $boundary = 'AaB03x';
        $body = "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"field1\"\r\n\r\n".
            "value1\r\n".
            "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"field2\"\r\n\r\n".
            "value2\r\n".
            "--$boundary--\r\n";

        $playwrightRequest = new MockRequest(
            url: 'http://localhost/upload',
            method: 'POST',
            headers: ['content-type' => 'multipart/form-data; boundary='.$boundary],
            postData: $body,
        );

        $request = $this->testCase->publicConvertToSymfonyRequest($playwrightRequest);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/upload', $request->getPathInfo());
        $this->assertSame('value1', $request->request->get('field1'));
        $this->assertSame('value2', $request->request->get('field2'));
        $this->assertSame($body, $request->getContent());
    }

    public function testIsBinaryContentType(): void
    {
        $this->assertFalse($this->testCase->publicIsBinaryContentType('text/plain'));
        $this->assertFalse($this->testCase->publicIsBinaryContentType('application/json'));
        $this->assertTrue($this->testCase->publicIsBinaryContentType('image/png'));
        $this->assertTrue($this->testCase->publicIsBinaryContentType('application/octet-stream'));
    }

    public function testPrepareFulfillOptionsForTextResponse(): void
    {
        $response = new Response('hello', 200, [
            'content-type' => 'text/plain; charset=utf-8',
            'x-custom' => ['a', 'b'],
        ]);

        $opts = $this->testCase->publicPrepareFulfillOptions($response);

        $this->assertSame(200, $opts['status']);
        $this->assertSame('hello', $opts['body']);
        $this->assertArrayHasKey('headers', $opts);
        $this->assertSame('a, b', $opts['headers']['x-custom']);
        $this->assertArrayNotHasKey('isBase64', $opts);
    }

    public function testPrepareFulfillOptionsForBinaryResponse(): void
    {
        $binary = random_bytes(16);
        $response = new Response($binary, 200, [
            'content-type' => 'image/png',
        ]);

        $opts = $this->testCase->publicPrepareFulfillOptions($response);

        $this->assertSame(200, $opts['status']);
        $this->assertTrue($opts['isBase64'] ?? false);
        $this->assertSame(base64_encode($binary), $opts['body']);
    }

    public function testConvertToSymfonyRequestHandlesHttpsRequest(): void
    {
        $playwrightRequest = new MockRequest(
            url: 'https://localhost/secure',
            method: 'GET',
            headers: [],
            postData: null,
        );

        $request = $this->testCase->publicConvertToSymfonyRequest($playwrightRequest);

        $this->assertEquals('on', $request->server->get('HTTPS'));
    }

    public function testFormatHeadersHandlesArrayValues(): void
    {
        $headers = [
            'content-type' => ['text/html', 'charset=utf-8'],
            'cache-control' => 'no-cache',
            'x-custom' => ['value1', 'value2', 'value3'],
        ];

        $formatted = $this->testCase->publicFormatHeaders($headers);

        $this->assertEquals('text/html, charset=utf-8', $formatted['content-type']);
        $this->assertEquals('no-cache', $formatted['cache-control']);
        $this->assertEquals('value1, value2, value3', $formatted['x-custom']);
    }

    public function testShouldInterceptRequestForLocalhost(): void
    {
        $url = parse_url('http://localhost/test');
        $this->assertTrue($this->testCase->publicShouldInterceptRequest($url));
    }

    public function testShouldInterceptRequestForLocalIp(): void
    {
        $url = parse_url('http://127.0.0.1/test');
        $this->assertTrue($this->testCase->publicShouldInterceptRequest($url));
    }

    public function testShouldInterceptRequestForTestDomain(): void
    {
        $url = parse_url('http://testapp.local/test');
        $this->assertTrue($this->testCase->publicShouldInterceptRequest($url));
    }

    public function testShouldNotInterceptExternalRequest(): void
    {
        $url = parse_url('http://google.com/search');
        $this->assertFalse($this->testCase->publicShouldInterceptRequest($url));
    }

    public function testGetBaseUrlReturnsDefault(): void
    {
        $this->assertEquals('http://localhost', $this->testCase->publicGetBaseUrl());
    }

    public function testIsHeadlessRespectsEnvironmentVariable(): void
    {
        putenv('PLAYWRIGHT_HEADLESS=false');
        $this->assertFalse($this->testCase->publicIsHeadless());

        putenv('PLAYWRIGHT_HEADLESS=true');
        $this->assertTrue($this->testCase->publicIsHeadless());

        putenv('PLAYWRIGHT_HEADLESS');
        $this->assertTrue($this->testCase->publicIsHeadless());
    }

    public function testSetUpDoesNotBootKernelOrStartBrowser(): void
    {
        $this->lifecycleTestCase->setTestClient(null);

        $this->lifecycleTestCase->callSetUp();

        $this->assertFalse(TestablePlaywrightTestCase::isKernelBooted());
        $this->assertFalse($this->browser->started);
    }

    public function testPrimaryClientIsCreatedLazilyAndReused(): void
    {
        $this->lifecycleTestCase->setTestClient(null);
        $this->lifecycleTestCase->callSetUp();

        $first = TestablePlaywrightTestCase::publicGetPlaywrightClient();
        $second = TestablePlaywrightTestCase::publicGetPlaywrightClient();

        $this->assertSame($first, $second);
        $this->assertTrue(TestablePlaywrightTestCase::isKernelBooted());
        $this->assertSame(1, $this->browser->sessionCount);

        $this->lifecycleTestCase->callTearDown();
    }

    public function testCreatePlaywrightClientAlwaysCreatesAnIsolatedClient(): void
    {
        $this->lifecycleTestCase->setTestClient(null);
        $this->lifecycleTestCase->callSetUp();

        $primary = TestablePlaywrightTestCase::publicGetPlaywrightClient();
        $first = TestablePlaywrightTestCase::publicCreatePlaywrightClient();
        $second = TestablePlaywrightTestCase::publicCreatePlaywrightClient();

        $this->assertNotSame($primary, $first);
        $this->assertNotSame($primary->context(), $first->context());
        $this->assertNotSame($first, $second);
        $this->assertNotSame($first->context(), $second->context());
        $this->assertSame(3, $this->browser->sessionCount);

        $this->lifecycleTestCase->callTearDown();
    }

    public function testCookieAndAuthMethodsDelegateToClient(): void
    {
        $this->lifecycleTestCase->publicSetCookie('name', 'value', ['path' => '/test']);
        $this->lifecycleTestCase->publicGetCookie('name', 'https://example.com');
        $this->lifecycleTestCase->publicClearCookies();
        $this->lifecycleTestCase->publicClearCookie('name', 'example.com', '/path');
        $this->lifecycleTestCase->publicAuthenticate('user', ['role' => 'admin']);
        $returned = $this->lifecycleTestCase->publicLogout('admin');

        $this->assertSame(
            [
                ['name', 'value', ['path' => '/test']],
            ],
            $this->client->calls['setCookie'] ?? []
        );

        $this->assertSame(
            [
                ['name', 'https://example.com'],
            ],
            $this->client->calls['getCookie'] ?? []
        );

        $this->assertSame([true], $this->client->calls['clearCookies'] ?? []);
        $this->assertSame(
            [
                ['name', 'example.com', '/path'],
            ],
            $this->client->calls['clearCookie'] ?? []
        );

        $this->assertSame(
            [
                ['user', ['role' => 'admin']],
            ],
            $this->client->calls['authenticate'] ?? []
        );

        $this->assertSame($this->lifecycleTestCase, $returned);
        $this->assertSame(['admin'], $this->client->calls['logout'] ?? []);
    }

    public function testLoginUserDelegatesToClientAndReturnsTheTestCase(): void
    {
        $user = new InMemoryUser('admin@example.test', null);

        $returned = $this->lifecycleTestCase->publicLoginUser($user, 'admin', ['role' => 'ROLE_ADMIN']);

        $this->assertSame($this->lifecycleTestCase, $returned);
        $this->assertSame(
            [
                [$user, 'admin', ['role' => 'ROLE_ADMIN']],
            ],
            $this->client->calls['loginUser'] ?? []
        );
    }

    public function testLastRequestAndResponseDelegatesToClient(): void
    {
        $request = new SymfonyRequest();
        $response = new SymfonyResponse('ok');

        $this->client->lastRequest = $request;
        $this->client->lastResponse = $response;

        $this->assertSame($request, $this->lifecycleTestCase->publicGetLastRequest());
        $this->assertSame($response, $this->lifecycleTestCase->publicGetLastResponse());
    }

    public function testLifecycleHooksAreCallable(): void
    {
        $request = new SymfonyRequest();
        $response = new SymfonyResponse('ok');

        $this->lifecycleTestCase->publicBeforeRequest($request);
        $this->lifecycleTestCase->publicAfterResponse($response);
        $this->lifecycleTestCase->publicLoadFixtures(['Fixture\\Class']);

        $this->assertTrue(true); // If no exception is thrown, hooks are callable.
    }

    public function testTearDownDoesNotStopBrowser(): void
    {
        $this->lifecycleTestCase->callTearDown();

        $this->assertFalse($this->browser->stopped, 'Browser should NOT be stopped during tearDown anymore');
        $this->assertSame(1, $this->browser->resetCount);
    }

    public function testTearDownAfterClassStopsBrowser(): void
    {
        TestablePlaywrightTestCase::setSharedBrowser($this->browser);

        TestablePlaywrightTestCase::tearDownAfterClass();

        $this->assertTrue($this->browser->stopped, 'Browser should be stopped during tearDownAfterClass');
    }

    public function testClientBrowserAndBaseUrlPropertiesAreRemoved(): void
    {
        $class = new \ReflectionClass(PlaywrightTestCase::class);

        $this->assertFalse($class->hasProperty('client'));
        $this->assertFalse($class->hasProperty('browser'));
        $this->assertFalse($class->hasProperty('baseUrl'));
        $this->assertFalse($class->hasProperty('page'));
    }

    public function testGetBaseUrlDelegatesToClient(): void
    {
        // getBaseUrl() delegates to client
        $result = $this->lifecycleTestCase->publicGetBaseUrl();

        // FakeClient has default baseUrl = 'http://localhost'
        $this->assertSame('http://localhost', $result);
    }

    public function testParsesCookieHeader(): void
    {
        $playwrightRequest = new MockRequest(
            url: 'http://localhost/test',
            method: 'GET',
            headers: ['cookie' => 'session=abc123; user=john'],
            postData: null,
        );

        $request = $this->testCase->publicConvertToSymfonyRequest($playwrightRequest);

        $this->assertSame('abc123', $request->cookies->get('session'));
        $this->assertSame('john', $request->cookies->get('user'));
    }

    public function testShouldInterceptRequestReturnsFalseForUrlWithoutHost(): void
    {
        // parse_url may return array without 'host' key
        $this->assertFalse($this->testCase->publicShouldInterceptRequest([]));
    }

    public function testPrepareFulfillOptionsHandlesRedirectResponse(): void
    {
        $response = new Response('', 302, [
            'location' => 'https://example.com/redirected',
        ]);

        $opts = $this->testCase->publicPrepareFulfillOptions($response);

        $this->assertSame(302, $opts['status']);
        $this->assertSame('https://example.com/redirected', $opts['headers']['location']);
    }

    public function testPrepareFulfillOptionsHandlesEmptyResponse(): void
    {
        $response = new Response('', 204);

        $opts = $this->testCase->publicPrepareFulfillOptions($response);

        $this->assertSame(204, $opts['status']);
        $this->assertSame('', $opts['body']);
    }

    public function testFormatHeadersPreservesEmptyArrays(): void
    {
        $headers = [
            'x-empty' => [],
            'x-single' => ['value'],
        ];

        $formatted = $this->testCase->publicFormatHeaders($headers);

        $this->assertSame('', $formatted['x-empty']);
        $this->assertSame('value', $formatted['x-single']);
    }
}

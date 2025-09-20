<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Network\Request;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\MockRequest;
use Playwright\Symfony\Tests\Fixtures\Tests\ConcretePlaywrightTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PlaywrightTestCase::class)]
class PlaywrightTestCaseTest extends TestCase
{
    private ConcretePlaywrightTestCase $testCase;

    protected function setUp(): void
    {
        $this->testCase = new ConcretePlaywrightTestCase('aa');
        // Set up the default intercepted hosts since the mock kernel doesn't have a proper container
        $this->testCase->setInterceptedHosts(['localhost', '127.0.0.1', 'testapp.local']);
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
}

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
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Tests\Fixtures\MockRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(RequestConverter::class)]
class RequestConverterTest extends TestCase
{
    public function testConvertsCookies(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/',
            method: 'GET',
            headers: ['cookie' => 'a=1; b=hello%20world'],
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('1', $symfony->cookies->get('a'));
        self::assertSame('hello world', $symfony->cookies->get('b'));
    }

    public function testMultipartFileUploadCreatesUploadedFile(): void
    {
        $boundary = 'XyZ123456';
        $body = "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"field\"\r\n\r\nvalue\r\n".
            "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"file\"; filename=\"test.txt\"\r\n".
            "Content-Type: text/plain\r\n\r\nHello file!\r\n".
            "--$boundary--\r\n";

        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/upload',
            method: 'POST',
            headers: ['content-type' => 'multipart/form-data; boundary='.$boundary],
            postData: $body,
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('value', $symfony->request->get('field'));
        $file = $symfony->files->get('file');
        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame('test.txt', $file->getClientOriginalName());
        self::assertSame('text/plain', $file->getClientMimeType());
    }

    public function testMultipartSupportsArrayFieldsAndUtf8Filenames(): void
    {
        $boundary = 'Boundary123';
        $binary = "Hello\0World\n";
        $body = "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"items[details][name]\"\r\n\r\nWidget\r\n".
            "--$boundary\r\n".
            "Content-Disposition: form-data; name=\"attachments[]\"; filename*=UTF-8''caf%C3%A9.txt\r\n".
            "Content-Type: text/plain\r\n\r\n$binary\r\n".
            "--$boundary--\r\n";

        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/upload',
            method: 'POST',
            headers: ['content-type' => 'multipart/form-data; boundary='.$boundary],
            postData: $body,
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        $items = $symfony->request->all('items');
        self::assertSame('Widget', $items['details']['name']);

        $attachments = $symfony->files->get('attachments');
        self::assertIsArray($attachments);
        self::assertCount(1, $attachments);
        $file = $attachments[0];
        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame('café.txt', $file->getClientOriginalName());
        self::assertSame('text/plain', $file->getClientMimeType());
        self::assertSame(rtrim($binary, "\r\n"), file_get_contents($file->getRealPath()));
    }

    public function testConvertsHttpMethods(): void
    {
        $converter = new RequestConverter();

        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
        foreach ($methods as $method) {
            $request = new MockRequest(
                url: 'http://localhost/api/users',
                method: $method,
            );

            $symfony = $converter->convertToSymfonyRequest($request);

            self::assertSame($method, $symfony->getMethod());
            self::assertSame($method, $symfony->server->get('REQUEST_METHOD'));
        }
    }

    public function testExtractsQueryParameters(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/search?q=test&page=2&filter[]=active&filter[]=new',
            method: 'GET',
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('test', $symfony->query->get('q'));
        self::assertSame('2', $symfony->query->get('page'));
        self::assertSame(['active', 'new'], $symfony->query->all('filter'));
    }

    public function testHandlesEmptyQueryString(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/api/users',
            method: 'GET',
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertEmpty($symfony->query->all());
    }

    public function testConvertsFormUrlencodedBody(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/submit',
            method: 'POST',
            headers: ['content-type' => 'application/x-www-form-urlencoded'],
            postData: 'name=John&email=john%40example.com&age=30',
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('John', $symfony->request->get('name'));
        self::assertSame('john@example.com', $symfony->request->get('email'));
        self::assertSame('30', $symfony->request->get('age'));
    }

    public function testConvertsJsonBody(): void
    {
        $converter = new RequestConverter();
        $json = json_encode(['name' => 'John', 'age' => 30], JSON_THROW_ON_ERROR);
        $request = new MockRequest(
            url: 'http://localhost/api/users',
            method: 'POST',
            headers: ['content-type' => 'application/json'],
            postData: $json,
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame($json, $symfony->getContent());
        self::assertSame('application/json', $symfony->headers->get('content-type'));
    }

    public function testMapsHeaders(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/',
            method: 'GET',
            headers: [
                'user-agent' => 'Mozilla/5.0',
                'accept' => 'application/json',
                'x-custom-header' => 'custom-value',
                'authorization' => 'Bearer token123',
            ],
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('Mozilla/5.0', $symfony->headers->get('user-agent'));
        self::assertSame('application/json', $symfony->headers->get('accept'));
        self::assertSame('custom-value', $symfony->headers->get('x-custom-header'));
        self::assertSame('Bearer token123', $symfony->headers->get('authorization'));
    }

    public function testHandlesContentTypeAndContentLengthHeaders(): void
    {
        $converter = new RequestConverter();
        $content = 'test body content';
        $request = new MockRequest(
            url: 'http://localhost/',
            method: 'POST',
            headers: [
                'content-type' => 'text/plain',
                'content-length' => (string) strlen($content),
            ],
            postData: $content,
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('text/plain', $symfony->server->get('CONTENT_TYPE'));
        self::assertSame((string) strlen($content), $symfony->server->get('CONTENT_LENGTH'));
    }

    public function testParsesUrlComponents(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'https://example.com:8443/path/to/resource?key=value#fragment',
            method: 'GET',
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('example.com', $symfony->server->get('SERVER_NAME'));
        self::assertSame('example.com', $symfony->server->get('HTTP_HOST'));
        self::assertSame(8443, $symfony->server->get('SERVER_PORT'));
        self::assertSame('on', $symfony->server->get('HTTPS'));
        self::assertSame('/path/to/resource?key=value', $symfony->server->get('REQUEST_URI'));
    }

    public function testHandlesHttpScheme(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/',
            method: 'GET',
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('off', $symfony->server->get('HTTPS'));
    }

    public function testHandlesHttpsScheme(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'https://localhost/',
            method: 'GET',
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame('on', $symfony->server->get('HTTPS'));
    }

    public function testUsesDefaultPortWhenNotSpecified(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'http://localhost/path',
            method: 'GET',
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        self::assertSame(80, $symfony->server->get('SERVER_PORT'));
    }

    public function testHandlesInvalidUrl(): void
    {
        $converter = new RequestConverter();
        $request = new MockRequest(
            url: 'not-a-valid-url',
            method: 'GET',
        );

        $symfony = $converter->convertToSymfonyRequest($request);

        // When parse_url fails, we fallback to defaults
        self::assertSame('localhost', $symfony->server->get('SERVER_NAME'));
        self::assertSame('not-a-valid-url', $symfony->server->get('REQUEST_URI'));
    }
}

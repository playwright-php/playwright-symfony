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
use Playwright\Symfony\Client\ResponseConverter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[CoversClass(ResponseConverter::class)]
class ResponseConverterTest extends TestCase
{
    private ResponseConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ResponseConverter();
    }

    public function testPrepareFulfillOptionsForTextResponse(): void
    {
        $response = new Response('hello', 200, [
            'content-type' => 'text/plain; charset=utf-8',
            'content-length' => '5',
            'x-custom' => ['a', 'b'],
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame(200, $opts['status']);
        $this->assertSame('hello', $opts['body']);
        $this->assertSame('text/plain; charset=utf-8', $opts['contentType']);
        $this->assertSame('a, b', $opts['headers']['x-custom']);
        $this->assertArrayNotHasKey('content-length', $opts['headers']);
        $this->assertArrayNotHasKey('Content-Length', $opts['headers']);
        $this->assertArrayNotHasKey('isBase64', $opts);
    }

    public function testPrepareFulfillOptionsForBinaryFileResponse(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pw_response_');
        $this->assertNotFalse($tmp);

        $binary = random_bytes(16);
        file_put_contents($tmp, $binary);

        $response = new BinaryFileResponse($tmp);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame($response->getStatusCode(), $opts['status']);
        $this->assertArrayHasKey('headers', $opts);

        @unlink($tmp);
    }

    public function testPrepareFulfillOptionsForStreamedResponse(): void
    {
        $response = new StreamedResponse(static function (): void {
            echo 'stream-content';
        });

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame($response->getStatusCode(), $opts['status']);
        $this->assertArrayHasKey('headers', $opts);
        if (array_key_exists('body', $opts)) {
            $this->assertIsString($opts['body']);
        }
    }

    public function testIsBinaryContentTypeDetectsBinaryAndNonBinaryTypes(): void
    {
        $this->assertFalse($this->converter->isBinaryContentType(null));
        $this->assertFalse($this->converter->isBinaryContentType('text/plain'));
        $this->assertFalse($this->converter->isBinaryContentType('application/json'));
        $this->assertFalse($this->converter->isBinaryContentType('application/vnd.api+json'));
        $this->assertFalse($this->converter->isBinaryContentType('image/svg+xml'));

        $this->assertTrue($this->converter->isBinaryContentType('image/png'));
        $this->assertTrue($this->converter->isBinaryContentType('application/octet-stream'));
        $this->assertTrue($this->converter->isBinaryContentType('application/pdf'));
    }

    public function testFormatHeadersJoinsArrayValues(): void
    {
        $headers = [
            'x-single' => 'value',
            'x-multi' => ['a', 'b'],
        ];

        $formatted = $this->converter->formatHeaders($headers);

        $this->assertSame('value', $formatted['x-single']);
        $this->assertSame('a, b', $formatted['x-multi']);
    }

    public function testPrepareFulfillOptionsEncodesBinaryBodyWhenContentTypeIsBinary(): void
    {
        $binary = random_bytes(8);
        $response = new Response($binary, 200, [
            'content-type' => 'image/png',
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame(200, $opts['status']);
        $this->assertTrue($opts['isBase64']);
        $this->assertSame(base64_encode($binary), $opts['body']);
    }

    public function testPrepareFulfillOptionsLeavesBodyAsTextForJson(): void
    {
        $payload = json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR);
        $response = new Response($payload, 200, [
            'content-type' => 'application/json',
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame(200, $opts['status']);
        $this->assertSame($payload, $opts['body']);
        $this->assertArrayNotHasKey('isBase64', $opts);
    }

    public function testPrepareFulfillOptionsForDifferentStatusCodes(): void
    {
        $statusCodes = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];

        foreach ($statusCodes as $code => $reason) {
            $response = new Response('', $code);

            $opts = $this->converter->prepareFulfillOptions($response);

            $this->assertSame($code, $opts['status'], "Failed for status code {$code}");
        }
    }

    public function testPrepareFulfillOptionsStripsContentLengthHeader(): void
    {
        $response = new Response('test content', 200, [
            'content-length' => '999',
            'Content-Length' => '999',
            'x-custom' => 'value',
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertArrayNotHasKey('content-length', $opts['headers']);
        $this->assertArrayNotHasKey('Content-Length', $opts['headers']);
        $this->assertArrayHasKey('x-custom', $opts['headers']);
    }

    public function testPrepareFulfillOptionsForEmptyResponse(): void
    {
        $response = new Response('', 204);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame(204, $opts['status']);
        $this->assertSame('', $opts['body']);
    }

    public function testPrepareFulfillOptionsForRedirectResponse(): void
    {
        $response = new Response('', 302, [
            'location' => 'https://example.com/redirected',
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame(302, $opts['status']);
        $this->assertSame('https://example.com/redirected', $opts['headers']['location']);
    }

    public function testPrepareFulfillOptionsHandlesMultipleHeaderValues(): void
    {
        $response = new Response('', 200, [
            'x-custom-multi' => ['value1', 'value2'],
            'vary' => ['Accept', 'Accept-Encoding'],
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame('value1, value2', $opts['headers']['x-custom-multi']);
        $this->assertSame('Accept, Accept-Encoding', $opts['headers']['vary']);
    }

    public function testPrepareFulfillOptionsWithNullContentType(): void
    {
        $response = new Response('plain text content', 200);
        $response->headers->remove('content-type');

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertArrayNotHasKey('contentType', $opts);
        $this->assertSame('plain text content', $opts['body']);
    }

    public function testFormatHeadersHandlesNullValues(): void
    {
        $headers = [
            'x-present' => 'value',
            'x-null' => null,
            'x-array-with-null' => ['a', null, 'b'],
        ];

        $formatted = $this->converter->formatHeaders($headers);

        $this->assertSame('value', $formatted['x-present']);
        $this->assertSame('', $formatted['x-null']);
        $this->assertSame('a, b', $formatted['x-array-with-null']);
    }

    public function testIsBinaryContentTypeWithCharset(): void
    {
        $this->assertFalse($this->converter->isBinaryContentType('text/html; charset=utf-8'));
        $this->assertFalse($this->converter->isBinaryContentType('application/json; charset=utf-8'));
        $this->assertTrue($this->converter->isBinaryContentType('image/png; quality=high'));
    }

    public function testIsBinaryContentTypeWithComplexMimeTypes(): void
    {
        $this->assertFalse($this->converter->isBinaryContentType('application/vnd.api+json'));
        $this->assertFalse($this->converter->isBinaryContentType('application/ld+json'));
        $this->assertFalse($this->converter->isBinaryContentType('application/hal+xml'));
        $this->assertTrue($this->converter->isBinaryContentType('application/vnd.ms-excel'));
    }

    public function testIsBinaryContentTypeTreatsStructuredHtmlSuffixAsText(): void
    {
        $this->assertFalse($this->converter->isBinaryContentType('application/vnd.live-component+html'));
        $this->assertFalse($this->converter->isBinaryContentType('application/vnd.example+html; charset=utf-8'));
    }

    public function testPrepareFulfillOptionsLeavesLiveComponentHtmlAsPlainText(): void
    {
        $html = '<div id="live-1">page 2</div>';
        $response = new Response($html, 200, [
            'content-type' => 'application/vnd.live-component+html',
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertSame($html, $opts['body']);
        $this->assertArrayNotHasKey('isBase64', $opts);
    }

    public function testPrepareFulfillOptionsForBinaryResponseWithContentType(): void
    {
        $binaryData = random_bytes(32);
        $response = new Response($binaryData, 200, [
            'content-type' => 'application/octet-stream',
        ]);

        $opts = $this->converter->prepareFulfillOptions($response);

        $this->assertTrue($opts['isBase64']);
        $this->assertSame(base64_encode($binaryData), $opts['body']);
        $this->assertSame('application/octet-stream', $opts['contentType']);
    }
}

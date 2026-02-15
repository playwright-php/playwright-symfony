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

namespace Playwright\Symfony\Tests\Http;

use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Http\LazyRequest;

class LazyRequestTest extends TestCase
{
    public function testUrlSupportsStringCallableAndDefault(): void
    {
        $req = new LazyRequest(['url' => 'http://example.com']);
        $this->assertSame('http://example.com', $req->url());

        $req = new LazyRequest(['url' => static fn () => 'http://lazy.test']);
        $this->assertSame('http://lazy.test', $req->url());

        $req = new LazyRequest([]);
        $this->assertSame('', $req->url());
    }

    public function testMethodSupportsStringCallableAndDefault(): void
    {
        $req = new LazyRequest(['method' => 'POST']);
        $this->assertSame('POST', $req->method());

        $req = new LazyRequest(['method' => static fn () => 'PUT']);
        $this->assertSame('PUT', $req->method());

        $req = new LazyRequest([]);
        $this->assertSame('GET', $req->method());
    }

    public function testHeadersNormalizesArrayValuesAndIgnoresInvalidEntries(): void
    {
        $data = [
            'headers' => [
                'X-Foo' => 'bar',
                'X-Num' => 123,
                0 => 'ignored',
                'X-Invalid' => ['nested'],
            ],
        ];

        $req = new LazyRequest($data);
        $headers = $req->headers();

        $this->assertSame([
            'X-Foo' => 'bar',
            'X-Num' => '123',
        ], $headers);
    }

    public function testHeadersReturnsEmptyArrayWhenNotArray(): void
    {
        $req = new LazyRequest(['headers' => 'not-an-array']);
        $this->assertSame([], $req->headers());
    }

    public function testPostDataSupportsStringCallableAndReturnsNullOtherwise(): void
    {
        $req = new LazyRequest(['postData' => 'raw']);
        $this->assertSame('raw', $req->postData());

        $req = new LazyRequest(['postData' => static fn () => 'lazy']);
        $this->assertSame('lazy', $req->postData());

        $req = new LazyRequest(['postData' => 123]);
        $this->assertNull($req->postData());
    }

    public function testPostDataJsonParsesValidJsonAndReturnsNullOnInvalid(): void
    {
        $data = ['foo' => 'bar'];
        $req = new LazyRequest(['postData' => json_encode($data, JSON_THROW_ON_ERROR)]);

        $this->assertSame($data, $req->postDataJSON());

        $req = new LazyRequest(['postData' => '{invalid-json']);
        $this->assertNull($req->postDataJSON());
    }

    public function testResourceTypeUsesDefaultAndCustomValue(): void
    {
        $req = new LazyRequest([]);
        $this->assertSame('document', $req->resourceType());

        $req = new LazyRequest(['resourceType' => 'xhr']);
        $this->assertSame('xhr', $req->resourceType());
    }

    public function testHeaderValueIsCaseInsensitiveAndSplitsCommaSeparatedValues(): void
    {
        $req = new LazyRequest([
            'headers' => [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Accept' => 'text/html, application/json ,  ',
                'X-Empty' => '  ,  ',
            ],
        ]);

        $this->assertSame('text/html; charset=UTF-8', $req->headerValue('content-type'));
        $this->assertSame('text/html', $req->headerValue('ACCEPT'));
        $this->assertSame('', $req->headerValue('X-Empty'));
        $this->assertNull($req->headerValue('Missing'));
    }

    public function testHeadersArraySplitsHeaderValues(): void
    {
        $req = new LazyRequest([
            'headers' => [
                'Accept' => 'text/html, application/json ,  ',
            ],
        ]);

        $headersArray = $req->headersArray();

        $this->assertSame([
            ['name' => 'Accept', 'value' => 'text/html'],
            ['name' => 'Accept', 'value' => 'application/json'],
        ], $headersArray);
    }

    public function testAllHeadersDelegatesToHeaders(): void
    {
        $req = new LazyRequest([
            'headers' => [
                'X-Test' => 'value',
            ],
        ]);

        $this->assertSame(['X-Test' => 'value'], $req->allHeaders());
    }

    public function testIsNavigationRequestCastsToBool(): void
    {
        $req = new LazyRequest(['isNavigationRequest' => 1]);
        $this->assertTrue($req->isNavigationRequest());

        $req = new LazyRequest([]);
        $this->assertFalse($req->isNavigationRequest());
    }

    public function testPostDataBufferReturnsStringOrNull(): void
    {
        $req = new LazyRequest(['postDataBuffer' => 'buffer']);
        $this->assertSame('buffer', $req->postDataBuffer());

        $req = new LazyRequest(['postDataBuffer' => 123]);
        $this->assertNull($req->postDataBuffer());
    }

    public function testFailureReturnsNormalizedArrayOrNull(): void
    {
        $req = new LazyRequest(['failure' => ['errorText' => 'Oops']]);
        $this->assertSame(['errorText' => 'Oops'], $req->failure());

        $req = new LazyRequest(['failure' => ['errorText' => 123]]);
        $this->assertNull($req->failure());

        $req = new LazyRequest(['failure' => 'not-an-array']);
        $this->assertNull($req->failure());
    }

    public function testFrameAndRedirectMethodsReturnNull(): void
    {
        $req = new LazyRequest([]);

        $this->assertNull($req->frame());
        $this->assertNull($req->redirectedFrom());
        $this->assertNull($req->redirectedTo());
        $this->assertNull($req->response());
    }

    public function testServiceWorkerReturnsRawValue(): void
    {
        $worker = new \stdClass();
        $req = new LazyRequest(['serviceWorker' => $worker]);

        $this->assertSame($worker, $req->serviceWorker());
    }

    public function testSizesReturnsZeroStructure(): void
    {
        $req = new LazyRequest([]);
        $sizes = $req->sizes();

        $this->assertSame([
            'requestBodySize' => 0,
            'requestHeadersSize' => 0,
            'responseBodySize' => 0,
            'responseHeadersSize' => 0,
        ], $sizes);
    }

    public function testTimingReturnsDefaultsWhenMissing(): void
    {
        $req = new LazyRequest([]);
        $timing = $req->timing();

        $this->assertSame(-1.0, $timing['startTime']);
        $this->assertSame(-1.0, $timing['responseEnd']);
    }

    public function testTimingNormalizesNumericValuesAndUsesMinusOneForInvalid(): void
    {
        $req = new LazyRequest([
            'timing' => [
                'startTime' => '10.5',
                'responseEnd' => 'not-numeric',
            ],
        ]);

        $timing = $req->timing();

        $this->assertSame(10.5, $timing['startTime']);
        $this->assertSame(-1.0, $timing['responseEnd']);
    }
}

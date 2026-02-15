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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Tests\Fixtures\MockRequest;

#[CoversNothing]
class MockRequestTest extends TestCase
{
    public function testBasicRequestCreation(): void
    {
        $request = new MockRequest(
            url: 'https://example.com',
            method: 'POST',
            headers: ['Content-Type' => 'application/json'],
            postData: '{"foo":"bar"}',
            resourceType: 'fetch',
        );

        self::assertSame('https://example.com', $request->url());
        self::assertSame('POST', $request->method());
        self::assertSame(['Content-Type' => 'application/json'], $request->headers());
        self::assertSame('{"foo":"bar"}', $request->postData());
        self::assertSame('fetch', $request->resourceType());
    }

    public function testDefaultValues(): void
    {
        $request = new MockRequest(url: 'https://example.com');

        self::assertSame('https://example.com', $request->url());
        self::assertSame('GET', $request->method());
        self::assertSame([], $request->headers());
        self::assertNull($request->postData());
        self::assertSame('document', $request->resourceType());
    }

    #[DataProvider('providePostDataForJsonConversion')]
    public function testPostDataJson(?string $postData, ?array $expectedJson): void
    {
        $request = new MockRequest(url: 'http://test.com', postData: $postData);
        self::assertSame($expectedJson, $request->postDataJSON());
    }

    public static function providePostDataForJsonConversion(): iterable
    {
        yield 'valid json' => [
            'postData' => '{"key":"value","nested":{"a":1}}',
            'expectedJson' => ['key' => 'value', 'nested' => ['a' => 1]],
        ];
        yield 'null post data' => [
            'postData' => null,
            'expectedJson' => null,
        ];
        yield 'empty string post data' => [
            'postData' => '',
            'expectedJson' => null,
        ];
        yield 'invalid json' => [
            'postData' => 'not-a-json-string',
            'expectedJson' => null,
        ];
    }

    public function testPostDataJsonWithValidJson(): void
    {
        $request = new MockRequest(
            url: 'http://test.com',
            postData: '{"key":"value","number":42}',
        );

        self::assertSame(['key' => 'value', 'number' => 42], $request->postDataJSON());
    }
}

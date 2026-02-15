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

use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Tests\Fixtures\Browser\FakePlaywrightBrowser;
use Playwright\Symfony\Tests\Fixtures\Client\FakePlaywrightClient;
use Playwright\Symfony\Tests\Fixtures\Tests\TestablePlaywrightTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PlaywrightTestCaseLifecycleTest extends TestCase
{
    private TestablePlaywrightTestCase $testCase;
    private FakePlaywrightClient $client;
    private FakePlaywrightBrowser $browser;

    protected function setUp(): void
    {
        $this->testCase = new TestablePlaywrightTestCase('dummy');
        $this->client = new FakePlaywrightClient();
        $this->browser = new FakePlaywrightBrowser();

        $this->testCase->setTestClient($this->client);
        $this->testCase->setTestBrowser($this->browser);
        $this->testCase->setTestLogger(new NullLogger());
    }

    public function testMagicPropertyThrowsForUnknownName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'unknown' does not exist");

        /* @phpstan-ignore-next-line - accessing undefined property intentionally */
        $this->testCase->unknown;
    }

    public function testCookieAndAuthMethodsDelegateToClient(): void
    {
        $this->testCase->publicSetCookie('name', 'value', ['path' => '/test']);
        $this->testCase->publicGetCookie('name', 'https://example.com');
        $this->testCase->publicClearCookies();
        $this->testCase->publicClearCookie('name', 'example.com', '/path');
        $this->testCase->publicAuthenticate('user', ['role' => 'admin']);
        $this->testCase->publicLogout();

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

        $this->assertSame([true], $this->client->calls['logout'] ?? []);
    }

    public function testLastRequestAndResponseDelegatesToClient(): void
    {
        $request = new SymfonyRequest();
        $response = new SymfonyResponse('ok');

        $this->client->lastRequest = $request;
        $this->client->lastResponse = $response;

        $this->assertSame($request, $this->testCase->publicGetLastRequest());
        $this->assertSame($response, $this->testCase->publicGetLastResponse());
    }

    public function testLifecycleHooksAreCallable(): void
    {
        $request = new SymfonyRequest();
        $response = new SymfonyResponse('ok');

        $this->testCase->publicBeforeRequest($request);
        $this->testCase->publicAfterResponse($response);
        $this->testCase->publicLoadFixtures(['Fixture\\Class']);

        $this->assertTrue(true); // If no exception is thrown, hooks are callable.
    }

    public function testTearDownDoesNotStopBrowser(): void
    {
        $this->testCase->setDebugLoggingFlag(true);

        $this->testCase->callTearDown();

        $this->assertFalse($this->browser->stopped, 'Browser should NOT be stopped during tearDown anymore');
    }

    public function testTearDownAfterClassStopsBrowser(): void
    {
        TestablePlaywrightTestCase::setSharedBrowser($this->browser);

        TestablePlaywrightTestCase::tearDownAfterClass();

        $this->assertTrue($this->browser->stopped, 'Browser should be stopped during tearDownAfterClass');
    }
}

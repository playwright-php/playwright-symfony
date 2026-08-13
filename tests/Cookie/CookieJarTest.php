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

namespace Playwright\Symfony\Tests\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Cookie\CookieJar;
use Playwright\Symfony\Tests\Client\Fixtures\FakeBrowserContext;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\Response;

#[CoversClass(CookieJar::class)]
final class CookieJarTest extends TestCase
{
    public function testSetWritesToTheContext(): void
    {
        $expires = time() + 3600;
        $context = new FakeBrowserContext();

        $this->createJar($context)->set(new Cookie(
            name: 'session',
            value: 'abc',
            expires: (string) $expires,
            path: '/admin',
            domain: 'example.com',
            secure: true,
            httponly: true,
            samesite: 'lax',
        ));

        $this->assertSame([[
            'name' => 'session',
            'value' => 'abc',
            'domain' => 'example.com',
            'path' => '/admin',
            'secure' => true,
            'httpOnly' => true,
            'expires' => $expires,
            // playwright capitalizes it
            'sameSite' => 'Lax',
        ]], $context->cookies);
    }

    public function testSetFallsBackToTheDefaultDomain(): void
    {
        $context = new FakeBrowserContext();

        $this->createJar($context)->set(new Cookie('no_domain', 'v'));

        $this->assertSame('localhost', $context->cookies[0]['domain']);
    }

    public function testAllReadsFromTheContext(): void
    {
        $expires = time() + 3600;
        $context = new FakeBrowserContext();
        $context->addCookies([
            // playwright reports -1 for a session cookie and may report a float timestamp
            ['name' => 'session', 'value' => 's', 'domain' => 'localhost', 'path' => '/', 'expires' => -1],
            ['name' => 'kept', 'value' => 'k', 'domain' => 'localhost', 'path' => '/', 'expires' => $expires + 0.5, 'sameSite' => 'Strict'],
        ]);

        $cookies = $this->createJar($context)->all();

        $this->assertCount(2, $cookies);
        $this->assertNull($cookies[0]->getExpiresTime());
        $this->assertSame((string) $expires, $cookies[1]->getExpiresTime());
        $this->assertSame('strict', $cookies[1]->getSameSite());
    }

    public function testGetMatchesNamePathAndDomain(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([
            ['name' => 'a', 'value' => 'root', 'domain' => 'example.com', 'path' => '/'],
            ['name' => 'b', 'value' => 'nested', 'domain' => 'example.com', 'path' => '/admin'],
        ]);

        $jar = $this->createJar($context);

        $this->assertSame('root', $jar->get('a')?->getValue());
        $this->assertSame('nested', $jar->get('b', '/admin')?->getValue());
        $this->assertNull($jar->get('b'), 'a cookie scoped to /admin does not match /');
        $this->assertNull($jar->get('a', '/', 'other.com'));
        $this->assertNull($jar->get('missing'));
    }

    public function testExpireRemovesEveryDomainAndPathVariant(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([
            ['name' => 'dupe', 'value' => '1', 'domain' => 'example.com', 'path' => '/'],
            ['name' => 'kept', 'value' => '2', 'domain' => 'example.com', 'path' => '/'],
        ]);

        $this->createJar($context)->expire('dupe');

        $this->assertSame(['kept'], array_column($context->cookies, 'name'));
    }

    public function testClearEmptiesTheContext(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([['name' => 'a', 'value' => '1', 'domain' => 'localhost', 'path' => '/']]);

        $this->createJar($context)->clear();

        $this->assertSame([], $context->cookies);
    }

    public function testAllValuesLetsTheBrowserScopeToTheUrl(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([['name' => 'a', 'value' => 'a b', 'domain' => 'example.com', 'path' => '/']]);

        $jar = $this->createJar($context);

        $this->assertSame(['a' => 'a b'], $jar->allValues('http://example.com/admin'));
        $this->assertSame(['http://example.com/admin'], $context->lastCookiesUrls);
        $this->assertSame(['a' => 'a%20b'], $jar->allRawValues('http://example.com/admin'));
    }

    public function testUpdateFromSetCookieWritesThroughToTheContext(): void
    {
        $context = new FakeBrowserContext();

        $this->createJar($context)->updateFromSetCookie(['a=1; path=/; domain=example.com'], 'http://example.com/');

        $this->assertSame('example.com', $context->cookies[0]['domain']);
        $this->assertSame('1', $context->cookies[0]['value']);
    }

    public function testUpdateFromResponseWritesThroughToTheContext(): void
    {
        $context = new FakeBrowserContext();
        $response = new Response('', 200, ['Set-Cookie' => ['a=1']]);

        $this->createJar($context)->updateFromResponse($response, 'http://example.com/');

        $this->assertSame('a', $context->cookies[0]['name']);
    }

    private function createJar(FakeBrowserContext $context): CookieJar
    {
        return new CookieJar($context, static fn (): string => 'localhost');
    }
}

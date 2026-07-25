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

namespace Playwright\Symfony\Tests\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Tests\Client\Fixtures\FakeBrowserContext;
use Playwright\Symfony\Util\CookieJarSync;
use Symfony\Component\BrowserKit\CookieJar;

#[CoversClass(CookieJarSync::class)]
final class CookieJarSyncTest extends TestCase
{
    public function testFromContextSeedsJar(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([
            ['name' => 'c1', 'value' => 'v1', 'domain' => 'localhost', 'path' => '/'],
            ['name' => 'c2', 'value' => 'v2', 'domain' => 'example.com', 'path' => '/app'],
        ]);

        $jar = new CookieJar();
        CookieJarSync::fromContext($jar, $context);

        $this->assertNotNull($jar->get('c1', '/', 'localhost'));
        $this->assertSame('v1', $jar->get('c1', '/', 'localhost')->getValue());

        $this->assertNotNull($jar->get('c2', '/app', 'example.com'));
        $this->assertSame('v2', $jar->get('c2', '/app', 'example.com')->getValue());
    }

    public function testFromContextAcceptsNumericExpires(): void
    {
        $future = time() + 3600;

        $context = new FakeBrowserContext();
        $context->addCookies([
            // Playwright reports "expires" as a number: -1 for session cookies,
            // a Unix timestamp (possibly float) otherwise.
            ['name' => 'session', 'value' => 's', 'domain' => 'localhost', 'path' => '/', 'expires' => -1],
            ['name' => 'float', 'value' => 'f', 'domain' => 'localhost', 'path' => '/', 'expires' => $future + 0.5],
            ['name' => 'int', 'value' => 'i', 'domain' => 'localhost', 'path' => '/', 'expires' => $future],
        ]);

        $jar = new CookieJar();
        CookieJarSync::fromContext($jar, $context);

        $session = $jar->get('session', '/', 'localhost');
        $this->assertNotNull($session);
        $this->assertNull($session->getExpiresTime());

        $float = $jar->get('float', '/', 'localhost');
        $this->assertNotNull($float);
        $this->assertSame((string) $future, $float->getExpiresTime());

        $int = $jar->get('int', '/', 'localhost');
        $this->assertNotNull($int);
        $this->assertSame((string) $future, $int->getExpiresTime());
    }

    public function testToJarFromUrlAcceptsNumericExpires(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([
            ['name' => 'site', 'value' => 'main', 'domain' => 'localhost', 'path' => '/', 'expires' => -1],
        ]);

        $jar = new CookieJar();
        CookieJarSync::toJarFromUrl($jar, $context, 'http://localhost/foo');

        $site = $jar->get('site');
        $this->assertNotNull($site);
        $this->assertNull($site->getExpiresTime());
    }

    public function testToJarFromUrlFiltersCookies(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([
            ['name' => 'site', 'value' => 'main', 'domain' => 'localhost', 'path' => '/'],
        ]);

        $jar = new CookieJar();
        CookieJarSync::toJarFromUrl($jar, $context, 'http://localhost/foo');

        $this->assertNotNull($jar->get('site'));
        $this->assertSame('main', $jar->get('site')->getValue());
    }

    public function testPlaywrightSessionCookieRemainsValidInBrowserKitJar(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([
            [
                'name' => 'session',
                'value' => 'session-id',
                'domain' => 'localhost',
                'path' => '/',
                'expires' => -1,
            ],
        ]);

        $jar = new CookieJar();
        CookieJarSync::toJarFromUrl($jar, $context, 'http://localhost/');

        $this->assertSame('session-id', $jar->get('session')?->getValue());
    }
}

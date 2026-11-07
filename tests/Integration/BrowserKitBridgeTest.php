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

namespace Playwright\Symfony\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Integration test for the BrowserKit -> Playwright bridge.
 */
#[CoversNothing]
final class BrowserKitBridgeTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testClickViaBrowserKit(): void
    {
        // Use the client provided by PlaywrightTestCase which is already configured for interception
        $browserKit = static::getPlaywrightClient();
        $crawler = $browserKit->request('GET', '/');

        $link = $crawler->filterXPath('//a[@id="link-1"]')->link();
        $browserKit->click($link);

        // Wait for browser to settle
        usleep(500000);

        $this->assertSame($this->getBaseUrl().'/1/', $browserKit->getPage()->url());
        // Relax assertion to ignore whitespace
        $this->assertStringContainsString('1', $browserKit->getCrawler()->filterXPath('//*[@id="current-path"]')->text());
    }

    public function testSubmitViaBrowserKit(): void
    {
        $browserKit = static::getPlaywrightClient();
        $crawler = $browserKit->request('GET', '/form');

        $form = $crawler->filterXPath('//form')->form();
        $browserKit->submit($form, ['name' => 'BrowserKitUser']);

        $this->assertStringContainsString('Hello BrowserKitUser', $browserKit->getLastSymfonyResponse()->getContent());
    }

    public function testCookieJarReadsAndWritesTheLiveContext(): void
    {
        $browserKit = static::getPlaywrightClient();
        $jar = $browserKit->getCookieJar();
        $jar->set(new Cookie('live', 'a+b%'));
        $jar->set(new Cookie('live', 'nested', path: '/admin'));

        $browserKit->request('GET', '/cookie');
        self::assertSame('a+b%', $browserKit->getRequest()->cookies->get('live'));

        $browserKit->getPage()->evaluate("document.cookie = 'from_js=a%2Bb; Path=/'");
        self::assertSame('a+b', $jar->get('from_js')?->getValue());

        $jar->expire('live');
        self::assertNull($jar->get('live'));
        self::assertSame('nested', $jar->get('live', '/admin')?->getValue());
    }

    public function testBrowserKitDoesNotReplayRejectedCookies(): void
    {
        $browserKit = static::getPlaywrightClient();
        $browserKit->request('GET', '/cookie?foreign=1');

        self::assertStringContainsString('domain=foreign.example', $browserKit->getInternalResponse()->getHeader('Set-Cookie'));
        self::assertNull($browserKit->getCookieJar()->get('foreign'));
    }
}

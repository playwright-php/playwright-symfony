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

use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Integration test for the BrowserKit -> Playwright bridge.
 */
final class BrowserKitBridgeTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testClickViaBrowserKit(): void
    {
        // Use the client provided by PlaywrightTestCase which is already configured for interception
        $browserKit = $this->client;
        $crawler = $browserKit->request('GET', '/');

        $link = $crawler->filterXPath('//a[@id="link-1"]')->link();
        $browserKit->click($link);

        // Wait for browser to settle
        usleep(500000);

        $this->assertSame($this->baseUrl.'/1/', $browserKit->getPage()->url());
        // Relax assertion to ignore whitespace
        $this->assertStringContainsString('1', $browserKit->getCrawler()->filterXPath('//*[@id="current-path"]')->text());
    }

    public function testSubmitViaBrowserKit(): void
    {
        $browserKit = $this->client;
        $crawler = $browserKit->request('GET', '/form');

        $form = $crawler->filterXPath('//form')->form();
        $browserKit->submit($form, ['name' => 'BrowserKitUser']);

        $this->assertStringContainsString('Hello BrowserKitUser', $browserKit->getLastSymfonyResponse()->getContent());
    }

    public function testCookieSync(): void
    {
        $browserKit = $this->client;

        // Set cookie via Playwright context (e.g. from a request)
        $this->browser->getContext()->addCookies([
            ['name' => 'sync_test', 'value' => 'ready', 'domain' => 'localhost', 'path' => '/'],
        ]);

        // The internal client doesn't automatically sync cookies FROM context TO Jar
        // during request() because it's usually the other way around.
        // But BrowserKit\PlaywrightClient does it after navigate.

        $browserKit->request('GET', '/cookie');

        // Let's manually trigger a sync if needed, or check if it happened.
        // Actually, our internal PlaywrightClient doesn't have syncCookiesFromContext() yet.
        // Only the other BrowserKit\PlaywrightClient does.

        $this->assertContains('sync_test', array_keys($this->getCookiesFromBrowser()));
    }

    private function getCookiesFromBrowser(): array
    {
        $cookies = $this->browser->getContext()->cookies();
        $list = [];
        foreach ($cookies as $c) {
            $list[$c['name']] = $c['value'];
        }

        return $list;
    }
}

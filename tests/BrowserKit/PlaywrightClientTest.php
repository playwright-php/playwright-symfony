<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP <https://github.com/playwright-php>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\BrowserKit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\BrowserKit\PlaywrightClient;
use Playwright\Symfony\Tests\Client\Fixtures\FakeBrowserContext;

#[CoversClass(PlaywrightClient::class)]
final class PlaywrightClientTest extends TestCase
{
    public function testFactorySeedsCookieJarAndNavigates(): void
    {
        $context = new FakeBrowserContext();
        $context->addCookies([
            ['name' => 'auth', 'value' => '1', 'domain' => 'example.test', 'path' => '/'],
        ]);

        $client = PlaywrightClient::fromContext($context);
        $client->request('GET', 'http://example.test/foo');

        $page = $client->getPage();
        self::assertSame('http://example.test/foo', $page?->url());

        $client->request(
            'POST',
            'http://example.test/form',
            ['field' => 'value'],
            [],
            ['HTTP_X_CUSTOM' => '1', 'PHP_AUTH_USER' => 'user', 'PHP_AUTH_PW' => 'pw']
        );

        self::assertSame(200, $client->getLastResponse()?->getStatusCode());
        self::assertSame('1', $context->extraHTTPHeaders['x-custom'] ?? null);
        self::assertSame([
            'username' => 'user',
            'password' => 'pw',
        ], $context->httpCredentials);
    }
}

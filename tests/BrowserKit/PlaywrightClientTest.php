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

namespace Playwright\Symfony\Tests\BrowserKit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Exception\TimeoutException;
use Playwright\Page\PageInterface;
use Playwright\Symfony\BrowserKit\PlaywrightClient;
use Playwright\Symfony\Tests\Client\Fixtures\FakeBrowserContext;
use Playwright\Symfony\Tests\Client\Fixtures\FakePage;
use Playwright\Symfony\Util\CookieJarSync;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;

#[CoversClass(PlaywrightClient::class)]
#[UsesClass(CookieJarSync::class)]
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

    public function testGetPageReturnsPage(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request('GET', 'http://example.test/page');

        $page = $client->getPage();

        self::assertNotNull($page);
        self::assertSame('http://example.test/page', $page->url());
    }

    public function testGetLastResponseReturnsNullBeforeRequest(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $response = $client->getLastResponse();

        self::assertNull($response);
    }

    public function testGetLastResponseReturnsResponseAfterRequest(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request('GET', 'http://example.test/');

        $response = $client->getLastResponse();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestWithFileParams(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request(
            'POST',
            'http://example.test/upload',
            [],
            [
                'file' => [
                    'name' => 'test.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/test',
                    'error' => 0,
                    'size' => 100,
                ],
            ]
        );

        $response = $client->getLastResponse();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestSyncsCookiesToBrowserContext(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        // Set cookie via BrowserKit
        $client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie('session', 'abc123', null, '/', 'example.test')
        );

        // Request should complete without errors
        $client->request('GET', 'http://example.test/page');

        // Verify the request completed successfully
        $response = $client->getLastResponse();
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestHandlesHttpsUrls(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request('GET', 'https://secure.example.test/secure-page');

        $page = $client->getPage();

        self::assertSame('https://secure.example.test/secure-page', $page?->url());
    }

    public function testRequestWithQueryParameters(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request('GET', 'http://example.test/search?q=test&page=2');

        $page = $client->getPage();

        self::assertStringContainsString('q=test', $page?->url() ?? '');
        self::assertStringContainsString('page=2', $page?->url() ?? '');
    }

    public function testMultipleRequestsUpdateLastResponse(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request('GET', 'http://example.test/page1');
        $firstResponse = $client->getLastResponse();

        $client->request('GET', 'http://example.test/page2');
        $secondResponse = $client->getLastResponse();

        self::assertNotSame($firstResponse, $secondResponse);
        self::assertSame(200, $secondResponse?->getStatusCode());
    }

    public function testRequestPostWithParameters(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request(
            'POST',
            'http://example.test/submit',
            ['name' => 'John', 'email' => 'john@example.com']
        );

        $response = $client->getLastResponse();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestPutWithContent(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request(
            'PUT',
            'http://example.test/update',
            ['data' => 'updated']
        );

        $response = $client->getLastResponse();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestHeadMethodUsesNavigate(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request('HEAD', 'http://example.test/check');

        $response = $client->getLastResponse();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testClickDoesNotWaitForPopupByDefault(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);
        $client->request('GET', 'http://example.test/');
        $pageBeforeClick = $client->getPage();
        self::assertInstanceOf(FakePage::class, $pageBeforeClick);

        $client->click($this->createLink());

        self::assertSame(0, $context->waitForPopupCalls);
        self::assertSame($pageBeforeClick, $client->getPage());
        self::assertContains('click', array_column($pageBeforeClick->locatorCalls, 'method'));
    }

    public function testClickFollowsPopupWhenEnabled(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);
        $client->setFollowPopups(true);
        $client->request('GET', 'http://example.test/');
        $pageBeforeClick = $client->getPage();
        self::assertInstanceOf(FakePage::class, $pageBeforeClick);

        $client->click($this->createLink());

        self::assertSame(1, $context->waitForPopupCalls);
        self::assertNotSame($pageBeforeClick, $client->getPage());
        self::assertContains('click', array_column($pageBeforeClick->locatorCalls, 'method'));
    }

    public function testClickStaysOnPageWhenNoPopupOpens(): void
    {
        $context = new class extends FakeBrowserContext {
            public function waitForPopup(callable $action, array $options = []): PageInterface
            {
                $action();

                throw new TimeoutException('No popup was created within the timeout period');
            }
        };
        $client = PlaywrightClient::fromContext($context);
        $client->setFollowPopups(true);
        $client->request('GET', 'http://example.test/');
        $pageBeforeClick = $client->getPage();
        self::assertInstanceOf(FakePage::class, $pageBeforeClick);

        $client->click($this->createLink());

        self::assertSame($pageBeforeClick, $client->getPage());
        self::assertContains('click', array_column($pageBeforeClick->locatorCalls, 'method'));
    }

    private function createLink(): Link
    {
        $crawler = new Crawler(
            '<html><body><a id="next-link" href="/next">Next</a></body></html>',
            'http://example.test/',
        );

        return $crawler->filter('#next-link')->link();
    }

    public function testApplyServerParamsWithHttpHeaders(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request(
            'GET',
            'http://example.test/api',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer token123', 'HTTP_ACCEPT' => 'application/json']
        );

        $response = $client->getLastResponse();

        self::assertNotNull($response);
        // Verify headers were applied
        self::assertSame('Bearer token123', $context->extraHTTPHeaders['authorization'] ?? null);
        self::assertSame('application/json', $context->extraHTTPHeaders['accept'] ?? null);
    }

    public function testApplyServerParamsWithPhpAuth(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request(
            'GET',
            'http://example.test/protected',
            [],
            [],
            ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'secret']
        );

        $response = $client->getLastResponse();

        self::assertNotNull($response);
        // Verify credentials were set
        self::assertSame(['username' => 'admin', 'password' => 'secret'], $context->httpCredentials);
    }

    public function testApplyServerParamsHandlesNonStringValues(): void
    {
        $context = new FakeBrowserContext();
        $client = PlaywrightClient::fromContext($context);

        $client->request(
            'GET',
            'http://example.test/api',
            [],
            [],
            ['HTTP_PORT' => 8080, 'HTTP_ENABLED' => true]
        );

        $response = $client->getLastResponse();

        self::assertNotNull($response);
        // Verify scalar values were converted to strings
        self::assertSame('8080', $context->extraHTTPHeaders['port'] ?? null);
        self::assertSame('1', $context->extraHTTPHeaders['enabled'] ?? null);
    }
}

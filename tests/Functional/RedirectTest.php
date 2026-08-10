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

namespace Playwright\Symfony\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class RedirectTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    #[DataProvider('redirectStatuses')]
    public function testKernelRedirectIsFollowedInProcess(int $status): void
    {
        $page = $this->visit('/redirect?status='.$status);

        self::assertSame($this->getBaseUrl().'/hello', $page->url());
        $this->assertPageContains('hello from app');
    }

    public function testKernelRedirectChainIsFollowedInProcess(): void
    {
        $page = $this->visit('/redirect?status=302&hops=2');

        self::assertSame($this->getBaseUrl().'/hello', $page->url());
        $this->assertPageContains('hello from app');
    }

    public function testKernelRedirectChainAfterLocatorClickIsFollowedInProcess(): void
    {
        $page = $this->visit('/hello');
        $page->setContent(sprintf(
            '<a href="%s/redirect?status=302&amp;hops=2">Redirect</a>',
            $this->getBaseUrl(),
        ));

        $page->locator('a')->click();

        self::assertSame($this->getBaseUrl().'/hello', $page->url());
        $this->assertPageContains('hello from app');
    }

    public function testKernelRedirectResponseCookieReachesTheBrowser(): void
    {
        $this->visit('/redirect?status=302&cookie=1');

        self::assertSame('yes', $this->getCookie('redirected'));
    }

    public function testFetchRedirectsUseBrowserSemantics(): void
    {
        $page = $this->visit('/hello');
        $cases = [
            [301, 'POST', 'GET'],
            [302, 'POST', 'GET'],
            [302, 'PUT', 'PUT'],
            [303, 'PUT', 'GET'],
            [307, 'POST', 'POST'],
            [308, 'PUT', 'PUT'],
        ];

        $result = $page->evaluate(<<<'JS'
            async ([baseUrl, cases]) => {
                const redirects = [];

                for (const [status, method] of cases) {
                    const url = new URL('/redirect', baseUrl);
                    url.searchParams.set('status', status);
                    url.searchParams.set('target', '/redirect/inspect');

                    const response = await fetch(url, {
                        body: 'payload',
                        headers: {'Content-Type': 'text/plain'},
                        method,
                    });

                    redirects.push({
                        body: await response.json(),
                        redirected: response.redirected,
                        status: response.status,
                        url: response.url,
                    });
                }

                const fetchRedirect = async (hops) => {
                    try {
                        const response = await fetch(`${baseUrl}/redirect?status=302&hops=${hops}`);

                        return {error: null, url: response.url};
                    } catch (error) {
                        return {error: error.message, url: null};
                    }
                };

                return {
                    limit: {
                        accepted: await fetchRedirect(9),
                        rejected: await fetchRedirect(10),
                    },
                    redirects,
                };
            }
            JS, [$this->getBaseUrl(), $cases]);

        foreach ($cases as $index => [$status, $method, $expectedMethod]) {
            $redirect = $result['redirects'][$index];

            self::assertSame(200, $redirect['status'], (string) $status);
            self::assertTrue($redirect['redirected'], (string) $status);
            self::assertSame($this->getBaseUrl().'/redirect/inspect', $redirect['url'], (string) $status);
            self::assertSame($expectedMethod, $redirect['body']['method'], (string) $status);
            self::assertSame($method === $expectedMethod ? 'payload' : '', $redirect['body']['body'], (string) $status);
        }

        self::assertSame(['error' => null, 'url' => $this->getBaseUrl().'/hello'], $result['limit']['accepted']);
        self::assertSame(['error' => 'Maximum redirect count exceeded', 'url' => null], $result['limit']['rejected']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function redirectStatuses(): iterable
    {
        foreach ([301, 302, 303, 307, 308] as $status) {
            yield (string) $status => [$status];
        }
    }
}

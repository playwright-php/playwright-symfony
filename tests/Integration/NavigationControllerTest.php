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

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class NavigationControllerTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRootPageShowsNavigationOptions(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        try {
            $request = Request::create('http://localhost/', 'GET');
            $response = $kernel->handle($request);

            self::assertSame(200, $response->getStatusCode());

            $content = (string) $response->getContent();
            $crawler = new Crawler($content);

            self::assertSame('Navigation Test', trim($crawler->filterXPath('//h1')->text()));
            self::assertSame('', trim($crawler->filterXPath("//*[@id='current-path']")->text()));

            $link1 = $crawler->filterXPath("//*[@id='link-1']")->attr('href');
            $link2 = $crawler->filterXPath("//*[@id='link-2']")->attr('href');

            self::assertSame('/1/', $link1);
            self::assertSame('/2/', $link2);
        } finally {
            $this->restoreExceptionHandlers();
            $kernel->shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testContinuationFromNestedPath(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        try {
            $request = Request::create('http://localhost/121/', 'GET');
            $response = $kernel->handle($request);

            self::assertSame(200, $response->getStatusCode());

            $content = (string) $response->getContent();
            $crawler = new Crawler($content);

            self::assertSame('121', trim($crawler->filterXPath("//*[@id='current-path']")->text()));

            $link1 = $crawler->filterXPath("//*[@id='link-1']")->attr('href');
            $link2 = $crawler->filterXPath("//*[@id='link-2']")->attr('href');

            self::assertSame('/1211/', $link1);
            self::assertSame('/1212/', $link2);
        } finally {
            $this->restoreExceptionHandlers();
            $kernel->shutdown();
        }
    }

    private function restoreExceptionHandlers(): void
    {
        try {
            $limit = 10;
            while ($limit-- > 0) {
                $previous = set_exception_handler(static function (): void {
                });
                restore_exception_handler();

                if (null === $previous) {
                    break;
                }

                restore_exception_handler();
            }
        } catch (\Throwable $e) {
            // ignore cleanup failures in tests
        }
    }
}

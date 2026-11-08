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

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Playwright\Assertions\Failure\AssertionException;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Playwright\Tracing\TracingInterface;
use Symfony\Component\HttpFoundation\Response;

#[CoversTrait(PlaywrightTestAssertionsTrait::class)]
class PlaywrightTestAssertionsTraitTest extends TestCase
{
    use PlaywrightTestAssertionsTrait;

    private ?PageInterface $page = null;
    private ?Response $response = null;
    private static ?int $visibilityTimeout = null;

    protected function setUp(): void
    {
        $this->page = null;
        $this->response = null;
        self::$visibilityTimeout = null;
    }

    public function testAssertPageContainsUsesPageContent(): void
    {
        $this->page = $this->createMock(PageInterface::class);
        $this->page->expects($this->once())
            ->method('content')
            ->willReturn('<html><body>Hello World</body></html>');

        $this->assertPageContains('Hello World');
    }

    public function testAssertPageNotContainsUsesPageContent(): void
    {
        $this->page = $this->createMock(PageInterface::class);
        $this->page->expects($this->once())
            ->method('content')
            ->willReturn('<html><body>Hello World</body></html>');

        $this->assertPageNotContains('Missing Text');
    }

    public function testAssertSelectorVisible(): void
    {
        $locator = $this->createMock(LocatorInterface::class);
        $locator->expects($this->exactly(2))->method('isVisible')->willReturnOnConsecutiveCalls(false, true);

        $this->page = $this->createPageWithTracing();
        $this->page->expects($this->once())
            ->method('locator')
            ->with('.visible')
            ->willReturn($locator);
        $locator->method('page')->willReturn($this->page);

        $this->assertSelectorVisible('.visible');
    }

    public function testAssertSelectorHidden(): void
    {
        $locator = $this->createMock(LocatorInterface::class);
        $locator->expects($this->exactly(2))->method('isVisible')->willReturnOnConsecutiveCalls(true, false);

        $this->page = $this->createPageWithTracing();
        $this->page->expects($this->once())
            ->method('locator')
            ->with('.hidden')
            ->willReturn($locator);
        $locator->method('page')->willReturn($this->page);

        $this->assertSelectorHidden('.hidden');
    }

    public function testAssertSelectorVisibleFailsAsAnAssertionWithinTheConfiguredTimeout(): void
    {
        self::$visibilityTimeout = 0;
        $locator = $this->createMock(LocatorInterface::class);
        $locator->method('isVisible')->willReturn(false);
        $this->page = $this->createPageWithTracing();
        $this->page->method('locator')->willReturn($locator);
        $locator->method('page')->willReturn($this->page);
        $start = hrtime(true);

        try {
            $this->assertSelectorVisible('.missing');
            self::fail('The visibility assertion passed.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString("Selector '.missing' is not visible.", $e->getMessage());
        }

        self::assertLessThan(1000, (hrtime(true) - $start) / 1_000_000);
    }

    public function testAssertSelectorHiddenFailsAsAnAssertion(): void
    {
        self::$visibilityTimeout = 0;
        $locator = $this->createMock(LocatorInterface::class);
        $locator->method('isVisible')->willReturn(true);
        $this->page = $this->createPageWithTracing();
        $this->page->method('locator')->willReturn($locator);
        $locator->method('page')->willReturn($this->page);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Selector '.shown' is not hidden.");

        $this->assertSelectorHidden('.shown');
    }

    public function testExpectAcceptsPageAndRecordsAssertion(): void
    {
        $this->page = $this->createPageWithTracing();
        $this->page->expects($this->once())->method('url')->willReturn('http://localhost/ready');

        $before = $this->numberOfAssertionsPerformed();

        $this->expect($this->page)->toHaveURL('http://localhost/ready');

        $this->assertSame($before + 1, $this->numberOfAssertionsPerformed());
    }

    public function testExpectUsesTheLocatorPageAndRecordsAssertion(): void
    {
        $this->page = $this->createMock(PageInterface::class);
        $this->page->expects($this->never())->method('context');
        $otherPage = $this->createPageWithTracing();
        $locator = $this->createMock(LocatorInterface::class);
        $locator->method('page')->willReturn($otherPage);
        $locator->expects($this->once())->method('isVisible')->willReturn(false);
        $before = $this->numberOfAssertionsPerformed();

        $this->expect($locator)->not()->toBeVisible();

        $this->assertSame($before + 1, $this->numberOfAssertionsPerformed());
    }

    public function testExpectHonorsTimeoutAndClosesTracingOnFailure(): void
    {
        $page = $this->createPageWithTracing();
        $locator = $this->createMock(LocatorInterface::class);
        $locator->method('page')->willReturn($page);
        $locator->expects($this->once())->method('isVisible')->willReturn(false);

        $this->expectException(AssertionException::class);
        $this->expectExceptionMessage('Expected locator to be visible.');

        $this->expect($locator)->withTimeout(0)->toBeVisible();
    }

    public function testAssertResponseStatusCode(): void
    {
        $this->response = new Response('', 201);
        $this->assertResponseStatusCode(201);
    }

    public function testAssertResponseIsRedirect(): void
    {
        $this->response = new Response('', 302);
        $this->assertResponseIsRedirect();
    }

    public function testInteractionHelpersDelegateToPage(): void
    {
        $this->page = $this->createMock(PageInterface::class);
        $locator = $this->createMock(LocatorInterface::class);
        $locator->expects($this->once())->method('click');
        $locator->expects($this->once())->method('fill')->with('value');
        $locator->expects($this->once())->method('selectOption')->with('option');
        $locator->expects($this->once())->method('check');
        $locator->expects($this->once())->method('uncheck');

        $this->page->expects($this->exactly(5))
            ->method('locator')
            ->willReturn($locator);

        $this->page->expects($this->once())
            ->method('waitForSelector')
            ->with('#wait', ['timeout' => 1000]);

        $this->page->expects($this->once())
            ->method('screenshot')
            ->with('/tmp/screenshot.png');

        $this->click('#button');
        $this->fill('#input', 'value');
        $this->select('#select', 'option');
        $this->check('#check');
        $this->uncheck('#uncheck');
        $this->waitForSelector('#wait', ['timeout' => 1000]);
        $this->screenshot('/tmp/screenshot.png');
    }

    protected function getPage(): PageInterface
    {
        return $this->page ?? $this->createMock(PageInterface::class);
    }

    protected function getVisibilityAssertionTimeout(): ?int
    {
        return self::$visibilityTimeout;
    }

    protected function getLastResponse(): ?Response
    {
        return $this->response;
    }

    private function createPageWithTracing(): PageInterface
    {
        $tracing = $this->createMock(TracingInterface::class);
        $tracing->expects($this->once())->method('group');
        $tracing->expects($this->once())->method('groupEnd');

        $context = $this->createMock(BrowserContextInterface::class);
        $context->method('tracing')->willReturn($tracing);

        $page = $this->createMock(PageInterface::class);
        $page->method('context')->willReturn($context);

        return $page;
    }
}

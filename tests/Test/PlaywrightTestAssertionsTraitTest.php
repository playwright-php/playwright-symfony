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
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Symfony\Component\HttpFoundation\Response;

class PlaywrightTestAssertionsTraitTest extends TestCase
{
    use PlaywrightTestAssertionsTrait;

    private ?PageInterface $page = null;
    private ?Response $response = null;

    protected function setUp(): void
    {
        $this->page = null;
        $this->response = null;
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

    public function testAssertSelectorExistsUsesLocator(): void
    {
        $this->page = $this->createMock(PageInterface::class);
        $locator = $this->createMock(LocatorInterface::class);
        $locator->expects($this->once())->method('count')->willReturn(1);

        $this->page->expects($this->once())
            ->method('locator')
            ->with('#main')
            ->willReturn($locator);

        $this->assertSelectorExists('#main');
    }

    public function testAssertSelectorNotExistsUsesLocator(): void
    {
        $this->page = $this->createMock(PageInterface::class);
        $locator = $this->createMock(LocatorInterface::class);
        $locator->expects($this->once())->method('count')->willReturn(0);

        $this->page->expects($this->once())
            ->method('locator')
            ->with('.missing')
            ->willReturn($locator);

        $this->assertSelectorNotExists('.missing');
    }

    public function testAssertSelectorVisible(): void
    {
        $this->page = $this->createMock(PageInterface::class);
        $locator = $this->createMock(LocatorInterface::class);
        $locator->expects($this->once())->method('isVisible')->willReturn(true);

        $this->page->expects($this->once())
            ->method('locator')
            ->with('.visible')
            ->willReturn($locator);

        $this->assertSelectorVisible('.visible');
    }

    public function testAssertSelectorHidden(): void
    {
        $this->page = $this->createMock(PageInterface::class);
        $locator = $this->createMock(LocatorInterface::class);
        $locator->expects($this->once())->method('isHidden')->willReturn(true);

        $this->page->expects($this->once())
            ->method('locator')
            ->with('.hidden')
            ->willReturn($locator);

        $this->assertSelectorHidden('.hidden');
    }

    public function testAssertSelectorTextContains(): void
    {
        $this->page = $this->createMock(PageInterface::class);
        $locator = $this->createMock(LocatorInterface::class);
        $locator->expects($this->once())->method('textContent')->willReturn('The Quick Brown Fox');

        $this->page->expects($this->once())
            ->method('locator')
            ->with('.text')
            ->willReturn($locator);

        $this->assertSelectorTextContains('.text', 'Brown Fox');
    }

    public function testAssertResponseStatusCode(): void
    {
        $this->response = new Response('', 201);
        $this->assertResponseStatusCode(201);
    }

    public function testAssertResponseIsSuccessful(): void
    {
        $this->response = new Response('', 200);
        $this->assertResponseIsSuccessful();
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

    protected function getLastResponse(): ?Response
    {
        return $this->response;
    }
}

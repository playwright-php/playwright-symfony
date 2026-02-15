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

namespace Playwright\Symfony\Test\Assert;

use Playwright\Page\PageInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
trait PlaywrightTestAssertionsTrait
{
    protected function assertPageContains(string $text): void
    {
        $content = $this->getPage()->content() ?? '';
        $this->assertStringContainsString($text, $content);
    }

    protected function assertPageNotContains(string $text): void
    {
        $content = $this->getPage()->content() ?? '';
        $this->assertStringNotContainsString($text, $content);
    }

    protected function assertSelectorExists(string $selector): void
    {
        $count = $this->getPage()->locator($selector)->count();
        $this->assertGreaterThan(0, $count, "Selector '$selector' not found");
    }

    protected function assertSelectorNotExists(string $selector): void
    {
        $count = $this->getPage()->locator($selector)->count();
        $this->assertSame(0, $count, "Selector '$selector' should not exist");
    }

    protected function assertSelectorVisible(string $selector): void
    {
        $this->assertTrue($this->getPage()->locator($selector)->isVisible(), "Selector '$selector' is not visible");
    }

    protected function assertSelectorHidden(string $selector): void
    {
        $this->assertTrue($this->getPage()->locator($selector)->isHidden(), "Selector '$selector' is not hidden");
    }

    protected function assertSelectorTextContains(string $selector, string $text): void
    {
        $this->assertStringContainsString($text, $this->getPage()->locator($selector)->textContent() ?? '', "Selector '$selector' does not contain text '$text'");
    }

    protected function assertResponseStatusCode(int $expectedCode): void
    {
        $response = $this->getLastResponse();
        $this->assertNotNull($response, 'No response available');
        $this->assertSame($expectedCode, $response->getStatusCode(), sprintf('Expected status code %d, got %d', $expectedCode, $response->getStatusCode()));
    }

    protected function assertResponseIsSuccessful(): void
    {
        $response = $this->getLastResponse();
        $this->assertNotNull($response, 'No response available');
        $this->assertTrue($response->isSuccessful(), sprintf('Expected successful response, got %d', $response->getStatusCode()));
    }

    protected function assertResponseIsRedirect(): void
    {
        $response = $this->getLastResponse();
        $this->assertNotNull($response, 'No response available');
        $this->assertTrue($response->isRedirect(), sprintf('Expected redirect response, got %d', $response->getStatusCode()));
    }

    protected function click(string $selector): void
    {
        $this->getPage()->locator($selector)->click();
    }

    protected function fill(string $selector, string $value): void
    {
        $this->getPage()->locator($selector)->fill($value);
    }

    protected function select(string $selector, string $value): void
    {
        $this->getPage()->locator($selector)->selectOption($value);
    }

    protected function check(string $selector): void
    {
        $this->getPage()->locator($selector)->check();
    }

    protected function uncheck(string $selector): void
    {
        $this->getPage()->locator($selector)->uncheck();
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function waitForSelector(string $selector, array $options = []): void
    {
        $this->getPage()->waitForSelector($selector, $options);
    }

    protected function screenshot(string $path): void
    {
        $this->getPage()->screenshot($path);
    }

    /**
     * Must be implemented by the class using this trait.
     */
    abstract protected function getPage(): PageInterface;

    /**
     * Must be implemented by the class using this trait.
     */
    abstract protected function getLastResponse(): ?Response;
}

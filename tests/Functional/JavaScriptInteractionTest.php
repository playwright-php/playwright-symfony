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

use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class JavaScriptInteractionTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testJavaScriptEvaluateReturnsValue(): void
    {
        $this->visit('/hello');

        // Execute JavaScript and get result
        $result = $this->getPage()->evaluate('() => 2 + 2');

        self::assertSame(4, $result);
    }

    public function testJavaScriptCanAccessDomElements(): void
    {
        $this->visit('/hello');

        // Get page content via JavaScript
        $content = $this->getPage()->evaluate('() => document.body.textContent');

        self::assertIsString($content);
        self::assertStringContainsString('hello', $content);
    }

    public function testJavaScriptCanModifyDom(): void
    {
        $this->visit('/hello');

        // Add new element via JavaScript
        $this->getPage()->evaluate('() => {
            const div = document.createElement("div");
            div.id = "test-element";
            div.textContent = "JavaScript Added This";
            document.body.appendChild(div);
        }');

        // Verify element exists
        $this->assertSelectorExists('#test-element');
        $this->assertPageContains('JavaScript Added This');
    }

    public function testWaitForSelectorWaitsForElement(): void
    {
        $this->visit('/hello');

        // Add element after delay
        $this->getPage()->evaluate('() => {
            setTimeout(() => {
                const div = document.createElement("div");
                div.id = "delayed-element";
                div.textContent = "Delayed Content";
                document.body.appendChild(div);
            }, 100);
        }');

        // Wait for element to appear
        $this->getPage()->waitForSelector('#delayed-element', ['timeout' => 5000]);

        $this->assertSelectorExists('#delayed-element');
    }
}

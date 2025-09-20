<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Integration\E2E;

use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class FormPostE2ETest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testFormSubmissionShowsGreeting(): void
    {
        // Visit the form page
        $this->visit('/form');

        // Verify form is displayed
        $this->assertPageContains('<form method="POST"');
        $this->assertPageContains('<input type="text" id="name" name="name"');

        // Fill and submit the form
        $this->page->locator('#name')->fill('Alice');
        $this->page->locator('button[type="submit"]')->click();

        // Verify the response shows the greeting
        $this->assertPageContains('Hello Alice');

        // Verify we're still on the form URL (POST to same URL)
        $this->assertStringContainsString('/form', $this->page->url());
    }

    public function testFormValidationRequiresName(): void
    {
        // Visit the form page
        $this->visit('/form');

        // Submit empty form (remove required attribute to test server-side validation)
        $this->page->evaluate('() => { document.querySelector("#name").removeAttribute("required"); }');
        $this->page->locator('button[type="submit"]')->click();

        // Should show validation error
        $this->assertPageContains('Name is required');
    }

    public function testFormHandlesSpecialCharacters(): void
    {
        // Visit the form page
        $this->visit('/form');

        // Fill with special characters
        $testName = 'Bob & Charlie <test>';
        $this->page->locator('#name')->fill($testName);
        $this->page->locator('button[type="submit"]')->click();

        // Verify the response shows the greeting with special chars (HTML encoded)
        $this->assertPageContains('Hello Bob &amp; Charlie <test>');
    }
}

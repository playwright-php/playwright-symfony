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

final class FormValidationTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testFormFillAndSubmitWithValidData(): void
    {
        $this->visit('/form');

        // Fill form with valid data
        $this->getPage()->locator('#name')->fill('John Doe');
        $this->getPage()->locator('button[type="submit"]')->click();

        // Assert success message
        $this->assertPageContains('Hello John Doe');

        $response = $this->getLastResponse();
        self::assertSame(200, $response->getStatusCode());
    }

    public function testFormSubmitWithEmptyFieldShowsValidationError(): void
    {
        $this->visit('/form');

        // Remove HTML5 validation to test server-side validation
        $this->getPage()->evaluate('() => { document.querySelector("#name").removeAttribute("required"); }');

        // Try to submit empty form
        $this->getPage()->locator('#name')->fill('');
        $this->getPage()->locator('button[type="submit"]')->click();

        // Should see validation error
        $this->assertPageContains('Name is required');

        $response = $this->getLastResponse();
        self::assertSame(400, $response->getStatusCode());
    }

    public function testFormFieldsAreAccessible(): void
    {
        $this->visit('/form');

        // Verify form elements exist
        $this->assertSelectorExists('form[method="POST"]');
        $this->assertSelectorExists('#name');
        $this->assertSelectorExists('button[type="submit"]');
    }
}

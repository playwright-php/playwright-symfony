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

final class FormSubmissionTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testFormPostSubmission(): void
    {
        // Visit the form page
        $this->visit('/form');

        // Verify we can see the form
        $this->assertPageContains('<form method="POST"');

        // Fill the form and submit
        $this->page->locator('input[name="name"]')->fill('TestUser');
        $this->page->locator('button[type="submit"]')->click();

        // Check if we get the expected response
        $this->assertPageContains('Hello TestUser');
    }
}

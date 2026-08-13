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

final class ErrorHandlingTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function test400ErrorFromFormValidation(): void
    {
        $this->visit('/form');

        // Remove HTML5 validation and submit empty
        $this->page->evaluate('() => { document.querySelector("#name").removeAttribute("required"); }');
        $this->page->locator('#name')->fill('');
        $this->page->locator('button[type="submit"]')->click();

        $response = $this->getLastResponse();

        self::assertSame(400, $response->getStatusCode());
        $this->assertPageContains('Name is required');
    }

    public function testSuccessfulRequestReturns200(): void
    {
        $this->visit('/hello');

        $response = $this->getLastResponse();

        self::assertSame(200, $response->getStatusCode());
        $this->assertPageContains('hello from app');
    }

    public function testNotFoundSubresourceDoesNotAbortThePage(): void
    {
        $this->visit('/page-with-missing-image');

        self::assertSame(404, $this->getLastResponse()->getStatusCode());
        $this->assertPageContains('Page loaded');
    }
}

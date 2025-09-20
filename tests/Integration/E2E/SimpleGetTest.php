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

final class SimpleGetTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testBasicGetRequest(): void
    {
        $this->visit('/hello');

        // Check that request was intercepted and handled by our kernel
        $lastRequest = $this->getLastRequest();
        $lastResponse = $this->getLastResponse();

        $this->assertNotNull($lastRequest, 'Request should have been intercepted');
        $this->assertNotNull($lastResponse, 'Response should have been generated');
        $this->assertSame('GET', $lastRequest->getMethod());
        $this->assertSame('/hello', $lastRequest->getPathInfo());
        $this->assertSame(200, $lastResponse->getStatusCode());

        // Check page content
        $this->assertPageContains('hello from app');
    }
}

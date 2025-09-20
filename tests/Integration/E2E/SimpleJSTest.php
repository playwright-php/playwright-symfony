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

final class SimpleJSTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testSimpleJavaScriptEvaluation(): void
    {
        $this->visit('/hello');

        // Test 1: Simple return value
        $result1 = $this->page->evaluate('() => { return 42; }');
        $this->assertSame(42, $result1);

        // Test 2: Object return
        $result2 = $this->page->evaluate('() => { return { test: "working" }; }');
        $this->assertSame(['test' => 'working'], $result2);

        // Test 3: Window location
        $result3 = $this->page->evaluate('() => { return window.location.href; }');
        $this->assertStringStartsWith('http://localhost', $result3);

        // Test 4: Simple async
        $result4 = $this->page->evaluate('async () => { return "async works"; }');
        $this->assertSame('async works', $result4);
    }
}

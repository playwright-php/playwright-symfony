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

use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class HelperAssertionsTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testAssertionHelpersCoverCommonActions(): void
    {
        $screenshotPath = sys_get_temp_dir().'/playwright-helper-'.uniqid().'.png';
        @unlink($screenshotPath);

        $this->visit('/helper-demo');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);
        self::assertRouteSame('helper_demo');
        self::assertPageTitleSame('Helper Demo');
        $this->assertPageContains('Helper Demo Ready');
        $this->assertPageNotContains('text that does not exist');
        $this->assertSelectorExists('#visible-text');
        $this->assertSelectorNotExists('#async-text');

        $this->waitForSelector('#async-text');
        $this->assertSelectorExists('#async-text');

        $this->fill('input[name="name"]', 'Simon');
        $this->select('select[name="color"]', 'green');
        $this->check('input[name="terms"]');
        $this->uncheck('input[name="terms"]');
        $this->check('input[name="terms"]');
        $this->click('#submit-btn');

        $this->assertPageContains('Form submitted: Simon / green / accepted');

        $this->screenshot($screenshotPath);
        $this->assertFileExists($screenshotPath);
        @unlink($screenshotPath);
    }
}

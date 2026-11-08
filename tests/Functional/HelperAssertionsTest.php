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

        $this->getPage()->evaluate('window.scheduleAsyncText()');
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

    public function testExpectRetriesAgainstTheLiveDom(): void
    {
        $page = $this->visit('/helper-demo');

        $inserted = $page->locator('#expect-inserted');
        $this->assertFalse($inserted->isAttached());
        $page->evaluate('() => window.scheduleExpectationInsert()');
        $this->expect($inserted)->toBeAttached();

        $text = $page->locator('#expect-text');
        $this->assertSame('Loading', $text->textContent());
        $page->evaluate('() => window.scheduleExpectationText()');
        $this->expect($text)->toContainText('Ready');

        $visible = $page->locator('#expect-visible');
        $this->assertFalse($visible->isVisible());
        $page->evaluate('() => window.scheduleExpectationShow()');
        $this->expect($visible)->toBeVisible();

        $removed = $page->locator('#expect-removed');
        $this->assertTrue($removed->isAttached());
        $page->evaluate('() => window.scheduleExpectationRemoval()');
        $this->expect($removed)->toHaveCount(0);
    }

    public function testVisibilityHelpersRetryAgainstTheLiveDom(): void
    {
        $page = $this->visit('/helper-demo');

        $visible = $page->locator('#expect-visible');
        $this->assertFalse($visible->isVisible());
        $page->evaluate('() => window.scheduleExpectationShow()');
        $this->assertSelectorVisible('#expect-visible');

        $hidden = $page->locator('#expect-hidden');
        $this->assertTrue($hidden->isVisible());
        $page->evaluate('() => window.scheduleExpectationHide()');
        $this->assertSelectorHidden('#expect-hidden');
    }
}

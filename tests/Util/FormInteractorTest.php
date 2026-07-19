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

namespace Playwright\Symfony\Tests\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Tests\Client\Fixtures\FakeBrowserContext;
use Playwright\Symfony\Tests\Client\Fixtures\FakePage;
use Playwright\Symfony\Util\FormInteractor;
use Playwright\Symfony\Util\XPathHelper;
use Symfony\Component\DomCrawler\Crawler;

#[CoversClass(FormInteractor::class)]
#[UsesClass(XPathHelper::class)]
final class FormInteractorTest extends TestCase
{
    public function testFillPopulatesVariousFields(): void
    {
        $html = '<html><body><form id="test-form">
            <input type="text" name="t" value="old">
            <input type="checkbox" name="c" value="1">
            <select name="s"><option value="o1">O1</option></select>
        </form></body></html>';

        $crawler = new Crawler($html, 'http://localhost');
        $form = $crawler->filterXPath('//form')->form();
        // Manually set some values in BrowserKit Form to simulate user input
        $form->setValues(['t' => 'new', 'c' => '1', 's' => 'o1']);

        $context = new FakeBrowserContext();
        $page = new FakePage($context);

        FormInteractor::fill($page, $form);

        $methods = array_column($page->locatorCalls, 'method');
        $this->assertContains('fill', $methods);
        $this->assertContains('check', $methods);
        $this->assertContains('selectOption', $methods);
    }

    public function testFillChecksTheRadioMatchingTheSelectedValue(): void
    {
        $html = '<html><body><form>
            <input type="radio" name="color" value="red">
            <input type="radio" name="color" value="green">
            <input type="radio" name="color" value="blue">
            <input type="submit">
        </form></body></html>';

        $crawler = new Crawler($html, 'http://localhost');
        $form = $crawler->filterXPath('//form')->form();
        $form->setValues(['color' => 'green']);

        $page = new FakePage(new FakeBrowserContext());

        FormInteractor::fill($page, $form);

        $checks = array_values(array_filter(
            $page->locatorCalls,
            static fn (array $call): bool => 'check' === $call['method'],
        ));

        $this->assertCount(1, $checks);
        $this->assertStringEndsWith(
            "//input[@type='radio'][@name='color'][@value='green']",
            $checks[0]['selector'],
        );
    }

    public function testFillChecksTheRadioNodeWhenItCarriesTheSelectedValue(): void
    {
        $html = '<html><body><form>
            <input type="radio" name="color" value="red">
            <input type="radio" name="color" value="green">
            <input type="submit">
        </form></body></html>';

        $crawler = new Crawler($html, 'http://localhost');
        $form = $crawler->filterXPath('//form')->form();
        $form->setValues(['color' => 'red']);

        $page = new FakePage(new FakeBrowserContext());

        FormInteractor::fill($page, $form);

        $checks = array_values(array_filter(
            $page->locatorCalls,
            static fn (array $call): bool => 'check' === $call['method'],
        ));

        $this->assertCount(1, $checks);
        // "red" is the value of the field node itself: the direct XPath is used.
        $this->assertStringEndsWith('/input[1]', $checks[0]['selector']);
    }
}

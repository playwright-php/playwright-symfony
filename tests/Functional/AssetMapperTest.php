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

/**
 * Functional test for AssetMapper integration with Playwright.
 *
 * This test verifies that:
 * - HTML pages with linked CSS assets are rendered correctly
 * - AssetMapper serves CSS files from the configured asset paths
 * - Styles are applied in the browser and can be inspected
 * - Playwright can verify computed styles in a real browser context
 *
 * This demonstrates full integration between Symfony's AssetMapper
 * and Playwright for testing frontend assets and styling.
 */
final class AssetMapperTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        // Use debug=false to prevent debug output in tests
        // AssetMapper dev server subscriber works in non-debug mode too
        return new TestKernel('test', false);
    }

    /**
     * Build a robust regex that matches computed CSS rgb()/rgba() values for a color.
     * Supports:
     * - rgb(r, g, b)
     * - rgba(r, g, b, 1)
     * - rgb/rgba with space-separated values and optional "/ 1" alpha (CSS Color 4).
     */
    private static function colorRegex(int $r, int $g, int $b): string
    {
        // separators can be comma or space; alpha can be omitted or explicitly set to 1 or 1.0
        // Example matches: rgb(52, 152, 219), rgba(52,152,219,1), rgb(52 152 219 / 1)
        return '~^rgba?\(\s*'.$r.'\s*[ ,]\s*'.$g.'\s*[ ,]\s*'.$b.'\s*(?:[\/,]\s*(?:1(?:\.0+)?))?\s*\)$~i';
    }

    public function testPageRendersWithAssetMapperCSS(): void
    {
        // Visit the demo page that uses AssetMapper for CSS
        $this->visit('/assetmapper');

        // Verify the page title and content are rendered
        $this->assertPageContains('AssetMapper Demo');
        $this->assertPageContains('This page uses AssetMapper for CSS assets');

        // Verify the heading element exists
        $heading = $this->getPage()->locator('h1.heading');
        self::assertTrue($heading->isVisible());
        self::assertSame('AssetMapper Demo', $heading->textContent());
    }

    public function testCSSFileIsLoadedAndApplied(): void
    {
        $this->visit('/assetmapper');

        // Verify the CSS link is present in the page (link elements are not visible; check presence)
        $cssLink = $this->getPage()->locator('link[rel="stylesheet"]');
        self::assertTrue($cssLink->count() > 0, 'Expected at least one stylesheet <link> element');

        $href = $cssLink->getAttribute('href');
        self::assertNotNull($href);
        $path = parse_url($href, PHP_URL_PATH) ?? $href;
        self::assertMatchesRegularExpression(
            '~^/assets/styles/app(?:-[A-Za-z0-9]+)?\.css$~',
            $path,
            'Expected stylesheet path to be served by AssetMapper with optional digest suffix'
        );

        $cssContents = $this->getPage()->evaluate('href => fetch(href).then(r => r.text())', [$href]);
        self::assertStringContainsString('.styled-box', $cssContents);
        self::assertStringContainsString('background-color: #3498db', $cssContents);
    }

    public function testStyledBoxHasCorrectStyling(): void
    {
        $this->visit('/assetmapper');

        // Locate the styled box element
        $styledBox = $this->getPage()->locator('.styled-box');
        self::assertTrue($styledBox->isVisible());

        // Verify the box contains expected text
        self::assertStringContainsString(
            'This box should have a blue background',
            $styledBox->textContent()
        );

        // Verify CSS styles are applied by checking computed styles
        $backgroundColor = $styledBox->evaluate('el => window.getComputedStyle(el).backgroundColor');

        // The blue color (#3498db) should be applied
        // RGB values: rgb(52, 152, 219) or rgba(52, 152, 219, 1)
        self::assertMatchesRegularExpression(
            self::colorRegex(52, 152, 219),
            $backgroundColor,
            'Expected blue background color to be applied from CSS'
        );

        // Verify text color is white
        $color = $styledBox->evaluate('el => window.getComputedStyle(el).color');
        self::assertMatchesRegularExpression(
            self::colorRegex(255, 255, 255),
            $color,
            'Expected white text color to be applied from CSS'
        );
    }

    public function testContainerHasCorrectLayoutStyles(): void
    {
        $this->visit('/assetmapper');

        // Verify the container has expected styling
        $container = $this->getPage()->locator('.container');
        self::assertTrue($container->isVisible());

        // Check background color is white
        $bgColor = $container->evaluate('el => window.getComputedStyle(el).backgroundColor');
        self::assertMatchesRegularExpression(
            self::colorRegex(255, 255, 255),
            $bgColor
        );

        // Check border-radius is applied
        $borderRadius = $container->evaluate('el => window.getComputedStyle(el).borderRadius');
        self::assertSame('8px', $borderRadius, 'Expected 8px border radius from CSS');
    }

    public function testMessageParagraphHasStyling(): void
    {
        $this->visit('/assetmapper');

        // Verify the message paragraph element
        $message = $this->getPage()->locator('p.message');
        self::assertTrue($message->isVisible());
        self::assertStringContainsString('AssetMapper', $message->textContent());

        // Check font size
        $fontSize = $message->evaluate('el => window.getComputedStyle(el).fontSize');

        // Should be 1.2rem which typically computes to around 19.2px (depending on base font size)
        self::assertMatchesRegularExpression(
            '/19\.2px|1\.2rem/',
            $fontSize,
            'Expected message to have 1.2rem font size'
        );
    }

    public function testPageHasCorrectDocumentStructure(): void
    {
        $this->visit('/assetmapper');

        // Verify proper HTML structure
        self::assertSame('AssetMapper Demo', $this->getPage()->title());

        // Check viewport meta tag
        $viewport = $this->getPage()->locator('meta[name="viewport"]');
        self::assertTrue($viewport->count() > 0);

        // Verify UTF-8 charset
        $charset = $this->getPage()->locator('meta[charset="UTF-8"]');
        self::assertTrue($charset->count() > 0);
    }

    public function testAssetMapperPageScreenshotIsSaved(): void
    {
        $this->visit('/assetmapper');

        $screenshotDir = __DIR__.'/../Fixtures/App/var/screenshots';
        if (!is_dir($screenshotDir) && !@mkdir($screenshotDir, 0777, true) && !is_dir($screenshotDir)) {
            self::fail(sprintf('Unable to create screenshot directory at %s', $screenshotDir));
        }

        $screenshotPath = $screenshotDir.'/assetmapper-demo.png';

        @unlink($screenshotPath);

        $this->getPage()->screenshot($screenshotPath, [
            'fullPage' => true,
        ]);

        self::assertFileExists($screenshotPath, 'Screenshot file should exist after capturing the page');
        self::assertGreaterThan(0, filesize($screenshotPath) ?: 0, 'Screenshot file should not be empty');
    }
}

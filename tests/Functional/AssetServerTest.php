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

final class AssetServerTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    /**
     * Test that CSS assets are loaded and served correctly.
     */
    public function testCssAssetLoadsCorrectly(): void
    {
        $this->visit('/asset-test');

        $content = $this->getPage()->content();
        static::assertStringContainsString('Asset Server Test Page', $content);

        // Check that CSS link is present in HTML
        $cssLinkCount = $this->getPage()->locator('link[rel="stylesheet"]')->count();
        static::assertGreaterThan(0, $cssLinkCount);

        // Check if the styling is applied by checking computed style
        $hasGradientBg = $this->getPage()->evaluate('() => {
            const box = document.querySelector(".asset-test-box");
            if (!box) return false;
            const style = window.getComputedStyle(box);
            return style.background.includes("gradient") || style.backgroundImage.includes("gradient");
        }');

        static::assertTrue($hasGradientBg, 'CSS gradient style should be applied');
    }

    /**
     * Test that JavaScript assets are loaded and executed.
     */
    public function testJavaScriptAssetLoadsAndExecutes(): void
    {
        $this->visit('/asset-test');

        // Check if JS function is available
        $functionExists = $this->getPage()->evaluate('() => typeof testAssetFunction === "function"');
        static::assertTrue($functionExists, 'JavaScript function should be defined');

        // Check if JS updated the DOM
        $jsResult = $this->getPage()->locator('#js-test-result')->textContent();
        static::assertSame('Asset script works!', $jsResult);
    }

    /**
     * Test that asset requests bypass kernel and return proper content.
     */
    public function testAssetRequestsBypassKernel(): void
    {
        $this->visit('/asset-test');

        // Fetch the CSS file directly
        $cssResponse = $this->getPage()->evaluate('async () => {
            const link = document.querySelector("link[rel=\"stylesheet\"]");
            if (!link) return null;
            const response = await fetch(link.href);
            return {
                status: response.status,
                contentType: response.headers.get("content-type"),
                content: await response.text()
            };
        }');

        static::assertSame(200, $cssResponse['status']);
        static::assertStringContainsString('text/css', $cssResponse['contentType']);
        static::assertStringContainsString('asset-test-box', $cssResponse['content']);
    }

    /**
     * Test that JS asset has correct content type.
     */
    public function testJavaScriptAssetHasCorrectContentType(): void
    {
        $this->visit('/asset-test');

        $jsResponse = $this->getPage()->evaluate('async () => {
            const script = document.querySelector("script[src]");
            if (!script) return null;
            const response = await fetch(script.src);
            return {
                status: response.status,
                contentType: response.headers.get("content-type"),
                hasContent: (await response.text()).length > 0
            };
        }');

        static::assertSame(200, $jsResponse['status']);
        static::assertStringContainsString('javascript', strtolower($jsResponse['contentType']));
        static::assertTrue($jsResponse['hasContent']);
    }

    /**
     * Test cache control headers are set for assets.
     */
    public function testAssetsCacheControlHeaders(): void
    {
        $this->visit('/asset-test');

        $headers = $this->getPage()->evaluate('async () => {
            const link = document.querySelector("link[rel=\"stylesheet\"]");
            if (!link) return null;
            const response = await fetch(link.href);
            return {
                cacheControl: response.headers.get("cache-control"),
                expires: response.headers.get("expires"),
                etag: response.headers.get("etag")
            };
        }');

        // AssetServer should set cache headers (check that at least one is present)
        $hasCacheHeaders = !empty($headers['cacheControl']) || !empty($headers['expires']) || !empty($headers['etag']);
        static::assertTrue($hasCacheHeaders, 'At least one cache header should be present');
    }

    /**
     * Test that assets work correctly via AssetMapper.
     */
    public function testAssetsWorkViaAssetMapper(): void
    {
        $this->visit('/asset-test');

        // Verify that the actual asset URLs work (they go through AssetMapper)
        $linksWork = $this->getPage()->evaluate('async () => {
            const cssLink = document.querySelector("link[rel=\"stylesheet\"]");
            const jsScript = document.querySelector("script[src]");
            
            if (!cssLink || !jsScript) return false;
            
            // Try to fetch both assets
            const [cssResponse, jsResponse] = await Promise.all([
                fetch(cssLink.href),
                fetch(jsScript.src)
            ]);
            
            return cssResponse.ok && jsResponse.ok;
        }');

        static::assertTrue($linksWork, 'Assets should load successfully via AssetMapper');
    }
}

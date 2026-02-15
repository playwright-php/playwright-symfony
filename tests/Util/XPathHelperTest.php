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
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Util\XPathHelper;

#[CoversClass(XPathHelper::class)]
final class XPathHelperTest extends TestCase
{
    public function testBuildXPathForSimpleElement(): void
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><body><div id="test"></div></body></html>');
        $div = $dom->getElementById('test');

        $xpath = XPathHelper::buildXPath($div);

        // Expected: //html[1]/body[1]/div[1]
        $this->assertSame('//html[1]/body[1]/div[1]', strtolower($xpath));
    }

    public function testBuildXPathForNestedSiblings(): void
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><body><ul><li>1</li><li>2</li><li id="target">3</li></ul></body></html>');
        $li = $dom->getElementById('target');

        $xpath = XPathHelper::buildXPath($li);

        $this->assertSame('//html[1]/body[1]/ul[1]/li[3]', strtolower($xpath));
    }

    public function testBuildXPathWithDifferentNames(): void
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<html><body><div></div><span></span><p id="target"></p></body></html>');
        $p = $dom->getElementById('target');

        $xpath = XPathHelper::buildXPath($p);

        // p is the 1st of its name
        $this->assertSame('//html[1]/body[1]/p[1]', strtolower($xpath));
    }
}

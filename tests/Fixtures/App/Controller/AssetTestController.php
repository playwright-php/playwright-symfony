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

namespace Playwright\Symfony\Tests\Fixtures\App\Controller;

use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;

final class AssetTestController
{
    public function __construct(
        private readonly Packages $packages,
    ) {
    }

    public function demo(): Response
    {
        $cssUrl = $this->packages->getUrl('styles/test.css');
        $jsUrl = $this->packages->getUrl('scripts/test.js');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Server Test</title>
    <link rel="stylesheet" href="{$cssUrl}">
    <script src="{$jsUrl}"></script>
</head>
<body>
    <div class="container">
        <h1>Asset Server Test Page</h1>
        <div class="asset-test-box">
            <p>If you see styling, CSS asset loaded correctly!</p>
        </div>
        <div id="js-test-result">JavaScript not loaded</div>
    </div>
    <script>
        if (typeof testAssetFunction === 'function') {
            document.getElementById('js-test-result').textContent = testAssetFunction();
        }
    </script>
</body>
</html>
HTML;

        return new Response($html);
    }
}

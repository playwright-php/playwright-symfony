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
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class AssetMapperController
{
    public function __construct(
        private readonly Packages $packages,
    ) {
    }

    public function demo(): Response
    {
        // Use AssetMapper to get the versioned asset URL
        $cssUrl = $this->packages->getUrl('styles/app.css');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AssetMapper Demo</title>
    <link rel="stylesheet" href="{$cssUrl}">
</head>
<body>
    <div class="container">
        <h1 class="heading">AssetMapper Demo</h1>
        <p class="message">This page uses AssetMapper for CSS assets</p>
        <div class="styled-box">
            <p>This box should have a blue background and white text.</p>
        </div>
    </div>
</body>
</html>
HTML;

        return new Response($html);
    }
}

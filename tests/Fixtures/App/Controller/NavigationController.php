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

use Symfony\Component\HttpFoundation\Response;

final class NavigationController
{
    public function navigate(string $path = ''): Response
    {
        // The path represents the history of clicks (e.g., "1", "12", "121", etc.)
        $history = '' === $path ? '' : $path;

        // Build the two navigation links (with trailing slash for Symfony routing)
        $link1 = '/'.$history.'1/';
        $link2 = '/'.$history.'2/';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Navigation Test</title>
</head>
<body>
    <h1>Navigation Test</h1>
    <p>Current path: <strong id="current-path">{$history}</strong></p>
    <div>
        <a href="{$link1}" id="link-1">Go to {$link1}</a>
        <br>
        <a href="{$link2}" id="link-2">Go to {$link2}</a>
    </div>
</body>
</html>
HTML;

        return new Response($html);
    }
}

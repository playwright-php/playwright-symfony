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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HelperDemoController
{
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $color = (string) $request->request->get('color', '');
            $terms = $request->request->has('terms') ? 'accepted' : 'declined';

            return new Response(sprintf('Form submitted: %s / %s / %s', $name ?: 'n/a', $color ?: 'n/a', $terms));
        }

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Helper Demo</title>
    <script>
        window.scheduleAsyncText = () => {
            setTimeout(() => {
                const asyncBlock = document.createElement('div');
                asyncBlock.id = 'async-text';
                asyncBlock.textContent = 'Async content ready';
                document.body.appendChild(asyncBlock);
            }, 120);
        };

        window.scheduleExpectationInsert = () => {
            setTimeout(() => {
                const block = document.createElement('div');
                block.id = 'expect-inserted';
                block.textContent = 'Inserted';
                document.body.appendChild(block);
            }, 120);
        };

        window.scheduleExpectationText = () => {
            setTimeout(() => {
                document.querySelector('#expect-text').textContent = 'Ready';
            }, 120);
        };

        window.scheduleExpectationShow = () => {
            setTimeout(() => {
                document.querySelector('#expect-visible').hidden = false;
            }, 120);
        };

        window.scheduleExpectationRemoval = () => {
            setTimeout(() => {
                document.querySelector('#expect-removed').remove();
            }, 120);
        };

        window.scheduleExpectationHide = () => {
            setTimeout(() => {
                document.querySelector('#expect-hidden').hidden = true;
            }, 120);
        };
    </script>
</head>
<body>
    <h1>Helper Demo Ready</h1>
    <div id="visible-text">Visible block</div>
    <div id="hidden-text" style="display:none;">Hidden block</div>
    <div id="expect-text">Loading</div>
    <div id="expect-visible" hidden>Waiting to become visible</div>
    <div id="expect-removed">Waiting to be removed</div>
    <div id="expect-hidden">Waiting to become hidden</div>

    <form id="helper-form" method="POST" action="/helper-demo">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="" />

        <label for="color">Favorite color</label>
        <select id="color" name="color">
            <option value="blue">Blue</option>
            <option value="green">Green</option>
            <option value="red">Red</option>
        </select>

        <label for="terms">
            <input type="checkbox" id="terms" name="terms" value="1" />
            Accept terms
        </label>

        <button type="submit" id="submit-btn">Send</button>
    </form>
</body>
</html>
HTML;

        return new Response($html);
    }
}

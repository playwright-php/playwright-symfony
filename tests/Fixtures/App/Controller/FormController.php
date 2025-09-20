<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FormController
{
    public function show(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name', '');
            if (!empty(trim($name))) {
                return new Response("Hello {$name}");
            }

            return new Response('Name is required', 400);
        }

        // Show form for GET requests
        return new Response('
            <html>
                <head><title>Form Test</title></head>
                <body>
                    <form method="POST" action="/form">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" required>
                        <button type="submit">Submit</button>
                    </form>
                </body>
            </html>
        ');
    }
}

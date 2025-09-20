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

final class ProtectedController
{
    public function index(Request $request): Response
    {
        $auth = $request->cookies->get('AUTH');
        if (null === $auth) {
            return new Response('Access Denied', 403);
        }

        return new Response('You are in', 200);
    }
}

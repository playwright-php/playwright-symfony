<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Response;

final class HelloController
{
    public function index(): Response
    {
        return new Response('hello from app');
    }
}

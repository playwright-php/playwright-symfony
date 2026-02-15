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

final class SessionController
{
    public function set(Request $request): Response
    {
        $session = $request->getSession();
        $key = $request->query->get('key', '');
        $value = $request->query->get('value', '');

        $session->set($key, $value);

        return new Response("Session set: {$key} = {$value}");
    }

    public function get(Request $request): Response
    {
        $session = $request->getSession();
        $key = $request->query->get('key', '');
        $value = $session->get($key);

        $displayValue = null === $value ? 'null' : $value;

        return new Response("Session value: {$displayValue}");
    }

    public function clear(Request $request): Response
    {
        $session = $request->getSession();
        $session->clear();

        return new Response('Session cleared');
    }
}

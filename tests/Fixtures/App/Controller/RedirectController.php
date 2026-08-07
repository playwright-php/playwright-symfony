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

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class RedirectController
{
    public function go(Request $request): RedirectResponse
    {
        $status = $request->query->getInt('status', 302);
        $hops = $request->query->getInt('hops');
        $target = $request->query->getString('target', '/hello');
        $location = $hops > 0
            ? sprintf('/redirect?status=%d&hops=%d&target=%s', $status, $hops - 1, rawurlencode($target))
            : $target;
        $response = new RedirectResponse($location, $status);

        if ($request->query->getBoolean('cookie')) {
            $response->headers->setCookie(Cookie::create('redirected', 'yes'));
        }

        return $response;
    }

    public function inspect(Request $request): JsonResponse
    {
        return new JsonResponse([
            'body' => $request->getContent(),
            'method' => $request->getMethod(),
        ]);
    }
}

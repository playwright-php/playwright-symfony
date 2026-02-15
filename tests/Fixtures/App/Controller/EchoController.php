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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class EchoController
{
    public function handle(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $json = null;
        if ('' !== $content && str_starts_with((string) $request->headers->get('content-type', ''), 'application/json')) {
            $json = json_decode($content, true);
        }
        $form = $request->request->all();

        return new JsonResponse([
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'headers' => [
                'x-test' => $request->headers->get('x-test'),
                'content-type' => $request->headers->get('content-type'),
            ],
            'body' => $json,
            'form' => $form,
        ]);
    }
}

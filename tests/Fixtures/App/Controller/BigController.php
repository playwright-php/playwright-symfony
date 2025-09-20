<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Response;

final class BigController
{
    public function index(): Response
    {
        $chunk = str_repeat('0123456789abcdef', 4096); // 64KB per chunk
        $body = str_repeat($chunk, 64); // ~4MB

        return new Response($body, 200, ['content-type' => 'text/plain; charset=utf-8']);
    }

    public function binary(): Response
    {
        // Create a small binary payload (PNG header + some random bytes)
        $binaryData = "\x89PNG\x0D\x0A\x1A\x0A".random_bytes(1024);

        return new Response($binaryData, 200, [
            'content-type' => 'image/png',
            'content-length' => (string) strlen($binaryData),
        ]);
    }
}

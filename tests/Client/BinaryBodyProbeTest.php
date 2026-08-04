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

namespace Playwright\Symfony\Tests\Client;

use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Tests\Fixtures\MockRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BinaryBodyProbeTest extends TestCase
{
    private const BOUNDARY = 'ProbeBoundary';

    public function testBinaryUploadSurvivesTheConversion(): void
    {
        $pngBytes = "\x89PNG\r\n\x1a\n\xff\xd8\xfe\x00\x01\x02";

        $rawBody = $this->multipartBody($pngBytes);
        $lossyBody = mb_convert_encoding($rawBody, 'UTF-8', 'UTF-8');

        self::assertNotSame($rawBody, $lossyBody, 'the fixture body must actually be lossy');

        $converter = new RequestConverter();
        $request = $converter->convertToSymfonyRequest(new MockRequest(
            url: 'http://localhost/upload',
            method: 'POST',
            headers: ['content-type' => 'multipart/form-data; boundary='.self::BOUNDARY],
            postData: $lossyBody,
            postDataBuffer: $rawBody,
        ));

        $file = $request->files->get('avatar');
        self::assertInstanceOf(UploadedFile::class, $file);

        self::assertSame(
            $pngBytes,
            file_get_contents($file->getPathname()),
            'the uploaded file reaching the kernel must be byte-identical to what the browser sent'
        );
    }

    private function multipartBody(string $fileBytes): string
    {
        $b = self::BOUNDARY;

        return "--$b\r\n"
            ."Content-Disposition: form-data; name=\"name\"\r\n\r\nAlice\r\n"
            ."--$b\r\n"
            ."Content-Disposition: form-data; name=\"avatar\"; filename=\"a.png\"\r\n"
            ."Content-Type: image/png\r\n\r\n"
            .$fileBytes."\r\n"
            ."--$b--\r\n";
    }
}

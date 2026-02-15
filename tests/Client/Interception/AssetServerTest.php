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

namespace Playwright\Symfony\Tests\Client\Interception;

use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Client\Interception\AssetFile;
use Playwright\Symfony\Client\Interception\AssetLocatorInterface;
use Playwright\Symfony\Client\Interception\AssetServer;

final class AssetServerTest extends TestCase
{
    public function testSupportsReturnsTrueForGetRequestWithMatchingPrefix(): void
    {
        $server = new AssetServer([], ['/assets', '/build']);

        self::assertTrue($server->supports('http://localhost/assets/app.css'));
        self::assertTrue($server->supports('http://localhost/build/app.js'));
        self::assertTrue($server->supports('http://localhost/assets/deep/nested/file.png'));
    }

    public function testSupportsReturnsFalseForNonMatchingPrefix(): void
    {
        $server = new AssetServer([], ['/assets', '/build']);

        self::assertFalse($server->supports('http://localhost/'));
        self::assertFalse($server->supports('http://localhost/api/users'));
        self::assertFalse($server->supports('http://localhost/images/logo.png'));
    }

    public function testSupportsReturnsTrueForHeadRequest(): void
    {
        $server = new AssetServer([], ['/assets']);

        self::assertTrue($server->supports('http://localhost/assets/app.css', 'HEAD'));
        self::assertTrue($server->supports('http://localhost/assets/app.css', 'head'));
    }

    public function testSupportsReturnsFalseForPostRequest(): void
    {
        $server = new AssetServer([], ['/assets']);

        self::assertFalse($server->supports('http://localhost/assets/app.css', 'POST'));
        self::assertFalse($server->supports('http://localhost/assets/app.css', 'PUT'));
        self::assertFalse($server->supports('http://localhost/assets/app.css', 'DELETE'));
        self::assertFalse($server->supports('http://localhost/assets/app.css', 'PATCH'));
    }

    public function testSupportsReturnsFalseForEmptyPath(): void
    {
        $server = new AssetServer([], ['/assets']);

        self::assertFalse($server->supports('http://localhost'));
        self::assertFalse($server->supports('http://localhost/'));
    }

    public function testSupportsNormalizesPrefixes(): void
    {
        $server = new AssetServer([], ['assets/', '/build', '//public/']);

        self::assertTrue($server->supports('http://localhost/assets/app.css'));
        self::assertTrue($server->supports('http://localhost/build/app.js'));
        self::assertTrue($server->supports('http://localhost/public/logo.png'));
    }

    public function testHandleReturnsNullWhenAssetNotFound(): void
    {
        $locator = $this->createMockLocator(null);
        $server = new AssetServer([$locator], ['/assets']);

        $result = $server->handle('http://localhost/assets/missing.css');

        self::assertNull($result);
    }

    public function testHandleReturnsNullForEmptyPath(): void
    {
        $server = new AssetServer([], ['/assets']);

        $result = $server->handle('http://localhost');

        self::assertNull($result);
    }

    public function testHandleReturnsFullOptionsForTextAsset(): void
    {
        $asset = new AssetFile(
            '/path/to/app.css',
            1234567890,
            25,
            'text/css',
            'body { color: red; }'
        );

        $locator = $this->createMockLocator($asset);
        $server = new AssetServer([$locator], ['/assets'], disableCache: true);

        $result = $server->handle('http://localhost/assets/app.css');

        self::assertIsArray($result);
        self::assertSame(200, $result['status']);
        self::assertSame('text/css', $result['contentType']);
        self::assertSame('body { color: red; }', $result['body']);
        self::assertArrayNotHasKey('isBase64', $result);

        self::assertArrayHasKey('headers', $result);
        self::assertSame('text/css', $result['headers']['content-type']);
        self::assertSame('no-store, max-age=0, must-revalidate', $result['headers']['cache-control']);
        self::assertSame('25', $result['headers']['content-length']);
    }

    public function testHandleReturnsBinaryAssetWithBase64Encoding(): void
    {
        $binaryContent = "\x89PNG\r\n\x1a\n";
        $asset = new AssetFile(
            '/path/to/logo.png',
            1234567890,
            strlen($binaryContent),
            'image/png',
            $binaryContent
        );

        $locator = $this->createMockLocator($asset);
        $server = new AssetServer([$locator], ['/assets']);

        $result = $server->handle('http://localhost/assets/logo.png');

        self::assertIsArray($result);
        self::assertSame('image/png', $result['contentType']);
        self::assertSame(base64_encode($binaryContent), $result['body']);
        self::assertTrue($result['isBase64']);
    }

    public function testHandleReturnsOnlyHeadersForHeadRequest(): void
    {
        $asset = new AssetFile(
            '/path/to/app.css',
            1234567890,
            100,
            'text/css',
            'body { color: red; }'
        );

        $locator = $this->createMockLocator($asset);
        $server = new AssetServer([$locator], ['/assets']);

        $result = $server->handle('http://localhost/assets/app.css', 'HEAD');

        self::assertIsArray($result);
        self::assertArrayNotHasKey('body', $result);
        self::assertArrayNotHasKey('isBase64', $result);
        self::assertArrayHasKey('headers', $result);
        self::assertSame('100', $result['headers']['content-length']);
    }

    public function testHandleUsesFirstLocatorThatFindsAsset(): void
    {
        $asset1 = new AssetFile(null, null, null, 'text/css', 'locator1');
        $asset2 = new AssetFile(null, null, null, 'text/css', 'locator2');

        $locator1 = $this->createMockLocator(null);
        $locator2 = $this->createMockLocator($asset1);
        $locator3 = $this->createMockLocator($asset2);

        $server = new AssetServer([$locator1, $locator2, $locator3], ['/assets']);

        $result = $server->handle('http://localhost/assets/app.css');

        self::assertIsArray($result);
        self::assertSame('locator1', $result['body']);
    }

    public function testHandleWithCacheEnabled(): void
    {
        $asset = new AssetFile(
            '/path/to/app.css',
            1234567890,
            25,
            'text/css',
            'body { }'
        );

        $locator = $this->createMockLocator($asset);
        $server = new AssetServer([$locator], ['/assets'], disableCache: false);

        $result = $server->handle('http://localhost/assets/app.css');

        self::assertIsArray($result);
        self::assertSame('public, max-age=31536000, immutable', $result['headers']['cache-control']);
    }

    public function testHandleWithLastModifiedHeader(): void
    {
        $mtime = 1609459200; // 2021-01-01 00:00:00 UTC
        $asset = new AssetFile(
            '/path/to/app.css',
            $mtime,
            10,
            'text/css',
            'test'
        );

        $locator = $this->createMockLocator($asset);
        $server = new AssetServer([$locator], ['/assets']);

        $result = $server->handle('http://localhost/assets/app.css');

        self::assertIsArray($result);
        self::assertArrayHasKey('last-modified', $result['headers']);
        self::assertSame('Fri, 01 Jan 2021 00:00:00 GMT', $result['headers']['last-modified']);
    }

    public function testHandleDetectsContentTypeAsBinary(): void
    {
        $tests = [
            'image/png' => true,
            'image/jpeg' => true,
            'image/gif' => true,
            'application/pdf' => true,
            'application/octet-stream' => true,
            'text/css' => false,
            'text/html' => false,
            'text/plain' => false,
            'application/json' => false,
            'application/javascript' => false,
            'application/xml' => false,
            'application/xhtml+xml' => false,
        ];

        foreach ($tests as $contentType => $expectedBinary) {
            $asset = new AssetFile(null, null, null, $contentType, 'content');
            $locator = $this->createMockLocator($asset);
            $server = new AssetServer([$locator], ['/assets']);

            $result = $server->handle('http://localhost/assets/file');

            if ($expectedBinary) {
                self::assertTrue($result['isBase64'] ?? false, "Expected {$contentType} to be binary");
            } else {
                self::assertArrayNotHasKey('isBase64', $result, "Expected {$contentType} to be text");
            }
        }
    }

    public function testHandleCalculatesContentLengthFromInlineContent(): void
    {
        $content = 'This is test content';
        $asset = new AssetFile(null, null, null, 'text/plain', $content);

        $locator = $this->createMockLocator($asset);
        $server = new AssetServer([$locator], ['/assets']);

        $result = $server->handle('http://localhost/assets/test.txt');

        self::assertSame((string) strlen($content), $result['headers']['content-length']);
    }

    public function testHandleWithAssetFromFilePath(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'asset_server_test_');
        self::assertIsString($tempFile);
        file_put_contents($tempFile, 'file content');

        $asset = new AssetFile($tempFile, filemtime($tempFile), filesize($tempFile), 'text/plain');

        $locator = $this->createMockLocator($asset);
        $server = new AssetServer([$locator], ['/assets']);

        try {
            $result = $server->handle('http://localhost/assets/test.txt');

            self::assertIsArray($result);
            self::assertSame('file content', $result['body']);
            self::assertSame((string) filesize($tempFile), $result['headers']['content-length']);
        } finally {
            @unlink($tempFile);
        }
    }

    private function createMockLocator(?AssetFile $returnValue): AssetLocatorInterface
    {
        return new class($returnValue) implements AssetLocatorInterface {
            public function __construct(private ?AssetFile $asset)
            {
            }

            public function locate(string $requestPath): ?AssetFile
            {
                return $this->asset;
            }
        };
    }
}

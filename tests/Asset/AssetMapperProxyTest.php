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

namespace Playwright\Symfony\Tests\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Asset\AssetMapperProxy;
use Playwright\Symfony\Client\Interception\AssetFile;

#[CoversClass(AssetMapperProxy::class)]
#[UsesClass(AssetFile::class)]
final class AssetMapperProxyTest extends TestCase
{
    public function testLocateReturnsNullWhenNoMapperProvided(): void
    {
        $proxy = new AssetMapperProxy(null);
        $this->assertNull($proxy->locate('/test.css'));
    }

    public function testLocateReturnsNullWhenAssetNotFound(): void
    {
        $mapper = new class {
            public function getAssetFromPublicPath()
            {
                return null;
            }

            public function allAssets()
            {
                return [];
            }
        };
        $proxy = new AssetMapperProxy($mapper);
        $this->assertNull($proxy->locate('/missing.css'));
    }

    public function testLocateUsesAssetIndexing(): void
    {
        $asset = (object) [
            'publicPath' => '/indexed.js',
            'sourcePath' => '/path/to/source.js',
            'content' => 'console.log("indexed");',
        ];

        $mapper = new class($asset) {
            public function __construct(private $asset)
            {
            }

            public function getAssetFromPublicPath()
            {
                return null;
            }

            public function allAssets()
            {
                return [$this->asset];
            }
        };

        $proxy = new AssetMapperProxy($mapper);
        $result = $proxy->locate('/indexed.js');

        $this->assertNotNull($result);
        $this->assertSame('console.log("indexed");', $result->getContent());
    }

    public function testExtractionFromMethods(): void
    {
        $asset = new class {
            public function getPublicPath()
            {
                return '/method.css';
            }

            public function getSourcePath()
            {
                return '/src/method.css';
            }

            public function getContent()
            {
                return 'body { color: blue; }';
            }

            public function getLastModified()
            {
                return 123456789;
            }
        };

        $mapper = new class($asset) {
            public function __construct(private $asset)
            {
            }

            public function getAssetFromPublicPath($path)
            {
                return '/method.css' === $path ? $this->asset : null;
            }
        };

        $proxy = new AssetMapperProxy($mapper);
        $result = $proxy->locate('/method.css');

        $this->assertNotNull($result);
        $this->assertSame('body { color: blue; }', $result->getContent());
        $this->assertSame(123456789, $result->getLastModified());
    }

    public function testExtractionFromProperties(): void
    {
        $asset = (object) [
            'publicPath' => '/prop.js',
            'path' => '/src/prop.js',
            'content' => 'alert(1);',
            'lastModified' => 111222333,
        ];

        $mapper = new class($asset) {
            public function __construct(private $asset)
            {
            }

            public function getAssetFromPublicPath($path)
            {
                return '/prop.js' === $path ? $this->asset : null;
            }
        };

        $proxy = new AssetMapperProxy($mapper);
        $result = $proxy->locate('/prop.js');

        $this->assertNotNull($result);
        $this->assertSame('alert(1);', $result->getContent());
        $this->assertSame(111222333, $result->getLastModified());
    }

    public function testFallbackToGetAsset(): void
    {
        $asset = (object) ['publicPath' => '/fallback.txt', 'content' => 'data'];
        $mapper = new class($asset) {
            public function __construct(private $asset)
            {
            }

            public function getAsset($path)
            {
                return 'fallback.txt' === $path ? $this->asset : null;
            }
        };

        $proxy = new AssetMapperProxy($mapper);
        $result = $proxy->locate('/fallback.txt');
        $this->assertNotNull($result);
        $this->assertSame('data', $result->getContent());
    }

    public function testLocateReturnsNullWhenNoSourceOrContent(): void
    {
        $asset = (object) ['publicPath' => '/nothing.txt'];
        $mapper = new class($asset) {
            public function __construct(private $asset)
            {
            }

            public function getAsset($path)
            {
                return $this->asset;
            }
        };

        $proxy = new AssetMapperProxy($mapper);
        $this->assertNull($proxy->locate('/nothing.txt'));
    }

    public function testMimeTypeResolutionFallback(): void
    {
        $asset = (object) [
            'publicPath' => '/style.css',
            'sourcePath' => '/some/file.css',
            'content' => 'css',
        ];
        $mapper = new class($asset) {
            public function __construct(private $asset)
            {
            }

            public function getAssetFromPublicPath($path)
            {
                return $this->asset;
            }
        };

        $proxy = new AssetMapperProxy($mapper);
        $result = $proxy->locate('/style.css');
        $this->assertSame('text/css', $result->getContentType());
    }

    public function testOctetStreamForUnknownExtension(): void
    {
        $asset = (object) ['publicPath' => '/binary.dat', 'content' => '0101'];
        $mapper = new class($asset) {
            public function __construct(private $asset)
            {
            }

            public function getAssetFromPublicPath($path)
            {
                return $this->asset;
            }
        };

        $proxy = new AssetMapperProxy($mapper);
        $result = $proxy->locate('/binary.dat');
        $this->assertSame('application/octet-stream', $result->getContentType());
    }
}

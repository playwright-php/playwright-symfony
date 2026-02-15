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
use Playwright\Symfony\Asset\FilesystemProxy;
use Playwright\Symfony\Client\Interception\AssetFile;

#[CoversClass(FilesystemProxy::class)]
#[UsesClass(AssetFile::class)]
final class FilesystemProxyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/pw_fs_test_'.uniqid();
        @mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testLocateFindsExistingFile(): void
    {
        file_put_contents($this->tempDir.'/test.txt', 'hello world');

        $proxy = new FilesystemProxy([$this->tempDir]);
        $asset = $proxy->locate('/test.txt');

        $this->assertNotNull($asset);
        $this->assertSame('hello world', $asset->getContent());
        $this->assertSame('text/plain', $asset->getContentType());
    }

    public function testLocateReturnsNullForMissingFile(): void
    {
        $proxy = new FilesystemProxy([$this->tempDir]);
        $this->assertNull($proxy->locate('/missing.txt'));
    }

    public function testLocateReturnsNullForDirectory(): void
    {
        @mkdir($this->tempDir.'/subdir');
        $proxy = new FilesystemProxy([$this->tempDir]);
        $this->assertNull($proxy->locate('/subdir'));
    }

    public function testLocateHandlesQueryString(): void
    {
        file_put_contents($this->tempDir.'/test.js', 'js');
        $proxy = new FilesystemProxy([$this->tempDir]);

        $asset = $proxy->locate('/test.js?v=123');
        $this->assertNotNull($asset);
        $this->assertSame('js', $asset->getContent());
    }

    public function testLocatePreventsDirectoryTraversal(): void
    {
        $proxy = new FilesystemProxy([$this->tempDir]);
        $this->assertNull($proxy->locate('/../etc/passwd'));
    }

    public function testLocateWithMultipleRoots(): void
    {
        $root1 = $this->tempDir.'/root1';
        $root2 = $this->tempDir.'/root2';
        @mkdir($root1);
        @mkdir($root2);

        file_put_contents($root2.'/found.txt', 'found me');

        $proxy = new FilesystemProxy([$root1, $root2]);
        $asset = $proxy->locate('/found.txt');

        $this->assertNotNull($asset);
        $this->assertSame('found me', $asset->getContent());
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

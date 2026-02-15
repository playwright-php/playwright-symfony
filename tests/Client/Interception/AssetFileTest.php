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

final class AssetFileTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $asset = new AssetFile(
            '/path/to/file.css',
            1234567890,
            1024,
            'text/css',
            'body { color: red; }'
        );

        self::assertSame('/path/to/file.css', $asset->getPath());
        self::assertSame(1234567890, $asset->getLastModified());
        self::assertSame(1024, $asset->getSize());
        self::assertSame('text/css', $asset->getContentType());
        self::assertTrue($asset->hasInlineContent());
        self::assertSame('body { color: red; }', $asset->getContent());
    }

    public function testConstructorWithNullValues(): void
    {
        $asset = new AssetFile(null, null, null, 'text/plain');

        self::assertNull($asset->getPath());
        self::assertNull($asset->getLastModified());
        self::assertNull($asset->getSize());
        self::assertSame('text/plain', $asset->getContentType());
    }

    public function testGetPathReturnsValue(): void
    {
        $asset = new AssetFile('/path/to/app.js', null, null, 'application/javascript');

        self::assertSame('/path/to/app.js', $asset->getPath());
    }

    public function testGetLastModifiedReturnsValue(): void
    {
        $mtime = 1609459200;
        $asset = new AssetFile(null, $mtime, null, 'text/plain');

        self::assertSame($mtime, $asset->getLastModified());
    }

    public function testGetSizeReturnsValue(): void
    {
        $asset = new AssetFile(null, null, 2048, 'image/png');

        self::assertSame(2048, $asset->getSize());
    }

    public function testGetContentTypeReturnsValue(): void
    {
        $asset = new AssetFile(null, null, null, 'application/json');

        self::assertSame('application/json', $asset->getContentType());
    }

    public function testHasInlineContentReturnsTrueWhenContentProvided(): void
    {
        $asset = new AssetFile(null, null, null, 'text/plain', 'inline content');

        self::assertTrue($asset->hasInlineContent());
    }

    public function testHasInlineContentReturnsFalseWhenNoContent(): void
    {
        $asset = new AssetFile('/path/to/file', null, null, 'text/plain');

        self::assertFalse($asset->hasInlineContent());
    }

    public function testGetContentReturnsInlineContent(): void
    {
        $content = 'This is inline content';
        $asset = new AssetFile(null, null, null, 'text/plain', $content);

        self::assertSame($content, $asset->getContent());
    }

    public function testGetContentReadsFromFilePathWhenNoInlineContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'asset_file_test_');
        self::assertIsString($tempFile);
        $content = 'File content from disk';
        file_put_contents($tempFile, $content);

        try {
            $asset = new AssetFile($tempFile, null, null, 'text/plain');

            self::assertSame($content, $asset->getContent());
        } finally {
            @unlink($tempFile);
        }
    }

    public function testGetContentReturnsEmptyStringWhenNoPathAndNoContent(): void
    {
        $asset = new AssetFile(null, null, null, 'text/plain');

        self::assertSame('', $asset->getContent());
    }

    public function testGetContentReturnsEmptyStringWhenFileDoesNotExist(): void
    {
        $asset = new AssetFile('/non/existent/file.txt', null, null, 'text/plain');

        self::assertSame('', $asset->getContent());
    }

    public function testGetContentHandlesBinaryData(): void
    {
        $binaryContent = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR";
        $asset = new AssetFile(null, null, null, 'image/png', $binaryContent);

        self::assertSame($binaryContent, $asset->getContent());
    }

    public function testMultipleCallsToGetContentReturnSameValue(): void
    {
        $content = 'cached content';
        $asset = new AssetFile(null, null, null, 'text/plain', $content);

        $first = $asset->getContent();
        $second = $asset->getContent();

        self::assertSame($first, $second);
        self::assertSame($content, $first);
    }

    public function testGetContentReadsFileEachTime(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'asset_file_test_');
        self::assertIsString($tempFile);
        file_put_contents($tempFile, 'initial content');

        try {
            $asset = new AssetFile($tempFile, null, null, 'text/plain');

            $first = $asset->getContent();
            self::assertSame('initial content', $first);

            // Modify the file
            file_put_contents($tempFile, 'modified content');

            $second = $asset->getContent();
            self::assertSame('modified content', $second);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testAssetWithPathAndContent(): void
    {
        // When both path and inline content are provided, inline content takes precedence
        $asset = new AssetFile(
            '/some/path.txt',
            null,
            null,
            'text/plain',
            'inline wins'
        );

        self::assertTrue($asset->hasInlineContent());
        self::assertSame('inline wins', $asset->getContent());
    }

    public function testAssetRepresentingEmptyFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'asset_file_test_');
        self::assertIsString($tempFile);
        file_put_contents($tempFile, '');

        try {
            $asset = new AssetFile($tempFile, filemtime($tempFile), 0, 'text/plain');

            self::assertSame('', $asset->getContent());
            self::assertSame(0, $asset->getSize());
        } finally {
            @unlink($tempFile);
        }
    }

    public function testAssetWithLargeFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'asset_file_test_');
        self::assertIsString($tempFile);
        $largeContent = str_repeat('a', 100000); // 100KB
        file_put_contents($tempFile, $largeContent);

        try {
            $asset = new AssetFile($tempFile, filemtime($tempFile), filesize($tempFile), 'text/plain');

            self::assertSame($largeContent, $asset->getContent());
            self::assertSame(100000, $asset->getSize());
        } finally {
            @unlink($tempFile);
        }
    }

    public function testAssetWithUtf8Content(): void
    {
        $utf8Content = 'Hello 世界 🌍';
        $asset = new AssetFile(null, null, null, 'text/plain; charset=UTF-8', $utf8Content);

        self::assertSame($utf8Content, $asset->getContent());
    }
}

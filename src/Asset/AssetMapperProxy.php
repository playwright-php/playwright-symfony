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

namespace Playwright\Symfony\Asset;

use Playwright\Symfony\Client\Interception\AssetFile;
use Playwright\Symfony\Client\Interception\AssetLocatorInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class AssetMapperProxy implements AssetLocatorInterface
{
    private ?object $mapper;
    private ?MimeTypes $mime;
    /** @var array<string, object>|null */
    private ?array $assetIndex = null;

    public function __construct(?object $assetMapper)
    {
        $this->mapper = $assetMapper;
        $this->mime = class_exists(MimeTypes::class) ? new MimeTypes() : null;
    }

    public function locate(string $requestPath): ?AssetFile
    {
        if (null === $this->mapper) {
            return null;
        }

        $normalizedPath = '/'.ltrim($requestPath, '/');
        $asset = $this->findAssetByPublicPath($normalizedPath);

        if (!$asset) {
            return null;
        }

        $sourcePath = $this->extractSourcePath($asset);
        $inlineContent = $this->extractContent($asset);

        if (null === $sourcePath && null === $inlineContent) {
            return null;
        }

        $lastModified = $this->guessLastModified($asset, $sourcePath);
        $size = $this->guessSize($sourcePath, $inlineContent);
        $contentType = $this->guessContentType($sourcePath, $normalizedPath);

        return new AssetFile($sourcePath, $lastModified, $size, $contentType, $inlineContent);
    }

    private function findAssetByPublicPath(string $publicPath): ?object
    {
        if (null === $this->mapper) {
            return null;
        }

        if (method_exists($this->mapper, 'getAssetFromPublicPath')) {
            $asset = $this->mapper->getAssetFromPublicPath($publicPath);
            if (null !== $asset) {
                \assert(is_object($asset));

                return $asset;
            }
        }

        if ($indexed = $this->getAssetIndex()) {
            return $indexed[$publicPath] ?? null;
        }

        if (method_exists($this->mapper, 'getAsset')) {
            $asset = $this->mapper->getAsset(ltrim($publicPath, '/'));
            if (null !== $asset) {
                \assert(is_object($asset));

                return $asset;
            }
        }

        return null;
    }

    /**
     * @return array<string, object>
     */
    private function getAssetIndex(): array
    {
        if (null !== $this->assetIndex) {
            return $this->assetIndex;
        }

        $this->assetIndex = [];

        if (null === $this->mapper || !method_exists($this->mapper, 'allAssets')) {
            return $this->assetIndex;
        }

        $allAssets = $this->mapper->allAssets();
        \assert(is_iterable($allAssets));

        foreach ($allAssets as $candidate) {
            \assert(is_object($candidate));
            $candidatePath = $this->extractPublicPath($candidate);
            if (null === $candidatePath || isset($this->assetIndex[$candidatePath])) {
                continue;
            }

            $this->assetIndex[$candidatePath] = $candidate;
        }

        return $this->assetIndex;
    }

    private function extractPublicPath(object $asset): ?string
    {
        foreach (['getPublicPath', 'publicPath'] as $method) {
            if (method_exists($asset, $method)) {
                $value = $asset->{$method}();
                if (is_string($value) && '' !== $value) {
                    return $value;
                }
            }
        }

        if (isset($asset->publicPath) && is_string($asset->publicPath)) {
            return $asset->publicPath;
        }

        return null;
    }

    private function extractSourcePath(object $asset): ?string
    {
        foreach (['getSourcePath', 'sourcePath', 'getPath'] as $method) {
            if (method_exists($asset, $method)) {
                $value = $asset->{$method}();
                if (is_string($value) && '' !== $value) {
                    return $value;
                }
            }
        }

        foreach (['sourcePath', 'path'] as $property) {
            if (isset($asset->{$property}) && is_string($asset->{$property}) && '' !== $asset->{$property}) {
                return $asset->{$property};
            }
        }

        return null;
    }

    private function extractContent(object $asset): ?string
    {
        foreach (['getContent', 'content'] as $method) {
            if (method_exists($asset, $method)) {
                $value = $asset->{$method}();
                if (is_string($value)) {
                    return $value;
                }
            }
        }

        if (isset($asset->content) && is_string($asset->content)) {
            return $asset->content;
        }

        return null;
    }

    private function guessLastModified(object $asset, ?string $sourcePath): ?int
    {
        foreach (['getLastModified', 'lastModified'] as $method) {
            if (method_exists($asset, $method)) {
                $value = $asset->{$method}();

                return is_numeric($value) ? (int) $value : null;
            }
        }

        if (isset($asset->lastModified) && is_numeric($asset->lastModified)) {
            return (int) $asset->lastModified;
        }

        if (null !== $sourcePath && is_file($sourcePath)) {
            return @filemtime($sourcePath) ?: null;
        }

        return null;
    }

    private function guessSize(?string $sourcePath, ?string $inlineContent): ?int
    {
        if (null !== $inlineContent) {
            return strlen($inlineContent);
        }

        if (null !== $sourcePath && is_file($sourcePath)) {
            $size = @filesize($sourcePath);

            return false !== $size ? $size : null;
        }

        return null;
    }

    private function guessContentType(?string $sourcePath, string $fallbackPath): string
    {
        $extension = null;

        if (null !== $sourcePath) {
            $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        }

        if (!$extension) {
            $extension = pathinfo($fallbackPath, PATHINFO_EXTENSION);
        }

        return $this->resolveMimeType($extension);
    }

    private function resolveMimeType(?string $extension): string
    {
        if ($extension) {
            $extension = strtolower($extension);

            if (in_array($extension, ['ts', 'mts', 'cts', 'tsx'], true)) {
                return 'text/javascript';
            }

            if (null !== $this->mime) {
                $types = $this->mime->getMimeTypes($extension);
                if (!empty($types)) {
                    return $types[0];
                }
            }

            $fallback = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'html' => 'text/html',
                'htm' => 'text/html',
                'txt' => 'text/plain; charset=UTF-8',
            ];

            if (isset($fallback[$extension])) {
                return $fallback[$extension];
            }
        }

        return 'application/octet-stream';
    }
}

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

namespace Playwright\Symfony\Client\Interception;

/**
 * Lightweight asset responder that serves requests directly from local locators.
 *
 * It is designed to work with Symfony's AssetMapper (via AssetMapperProxy)
 * but can aggregate any number of locators (filesystem, custom resolvers, etc.).
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final class AssetServer
{
    /** @var AssetLocatorInterface[] */
    private array $locators = [];

    /** @var string[] */
    private array $prefixes = [];

    private bool $disableCache;

    /**
     * @param iterable<AssetLocatorInterface> $locators
     * @param string[]                        $prefixes
     */
    public function __construct(iterable $locators, array $prefixes, bool $disableCache = true)
    {
        foreach ($locators as $locator) {
            $this->locators[] = $locator;
        }

        $normalized = [];
        foreach ($prefixes as $prefix) {
            $normalized[] = $this->normalizePrefix($prefix);
        }
        $this->prefixes = array_values(array_unique($normalized));
        $this->disableCache = $disableCache;
    }

    public function supports(string $url, string $method = 'GET'): bool
    {
        if (!in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (false === $path || null === $path || '' === $path) {
            return false;
        }

        foreach ($this->prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates Playwright fulfill options for the asset located at $url.
     *
     * @return array<string, mixed>|null
     */
    public function handle(string $url, string $method = 'GET'): ?array
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (false === $path || null === $path || '' === $path) {
            return null;
        }

        $asset = $this->locate(rtrim($path, '/'));
        if (null === $asset) {
            return null;
        }

        return $this->buildFulfillOptions($asset, strtoupper($method));
    }

    private function locate(string $requestPath): ?AssetFile
    {
        foreach ($this->locators as $locator) {
            $asset = $locator->locate($requestPath);
            if (null !== $asset) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFulfillOptions(AssetFile $asset, string $method): array
    {
        $headers = $this->buildHeaders($asset);
        $options = [
            'status' => 200,
            'headers' => $headers,
            'contentType' => $asset->getContentType(),
        ];

        if ('HEAD' !== $method) {
            $body = $asset->getContent();
            if ($this->isBinaryContentType($asset->getContentType())) {
                $options['body'] = base64_encode($body);
                $options['isBase64'] = true;
            } else {
                $options['body'] = $body;
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(AssetFile $asset): array
    {
        $headers = [
            'content-type' => $asset->getContentType(),
        ];

        if ($this->disableCache) {
            $headers['cache-control'] = 'no-store, max-age=0, must-revalidate';
        } else {
            $headers['cache-control'] = 'public, max-age=31536000, immutable';
        }

        if (null !== ($mtime = $asset->getLastModified())) {
            $headers['last-modified'] = gmdate('D, d M Y H:i:s', $mtime).' GMT';
        }

        $size = $asset->getSize();
        if (null === $size) {
            $content = $asset->hasInlineContent() ? $asset->getContent() : null;
            if (null !== $content) {
                $size = strlen($content);
            } elseif (null !== $asset->getPath() && is_file($asset->getPath())) {
                $size = @filesize($asset->getPath()) ?: null;
            }
        }

        if (null !== $size) {
            $headers['content-length'] = (string) $size;
        }

        return $headers;
    }

    private function normalizePrefix(string $prefix): string
    {
        $trimmed = '/'.ltrim($prefix, '/');

        return rtrim($trimmed, '/');
    }

    private function isBinaryContentType(?string $contentType): bool
    {
        if (null === $contentType) {
            return false;
        }

        $contentType = strtolower($contentType);

        $textPrefixes = [
            'text/',
            'application/json',
            'application/javascript',
            'application/x-www-form-urlencoded',
            'application/xml',
            'application/xhtml+xml',
        ];

        foreach ($textPrefixes as $prefix) {
            if (str_starts_with($contentType, $prefix)) {
                return false;
            }
        }

        return true;
    }
}

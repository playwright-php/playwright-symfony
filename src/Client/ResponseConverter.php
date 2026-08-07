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

namespace Playwright\Symfony\Client;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Converts Symfony responses to Playwright fulfill options.
 *
 * Handles regular, binary and streamed responses so that Playwright
 * receives proper bytes and content type. This is critical for assets
 * like CSS/JS/images returned by AssetMapper and other responders.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
class ResponseConverter
{
    /**
     * @return array<string, mixed>
     */
    public function prepareFulfillOptions(SymfonyResponse $response): array
    {
        $headers = $this->formatHeaders($response->headers->all());
        $contentType = $response->headers->get('content-type') ?: null;

        $body = $response->getContent();

        // BinaryFileResponse does not expose content via getContent()
        if ((false === $body || '' === $body) && $response instanceof BinaryFileResponse) {
            $file = $response->getFile();
            $path = $file->getPathname();
            $body = @file_get_contents($path) ?: '';
            if (!$contentType) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                $map = [
                    'css' => 'text/css; charset=UTF-8',
                    'js' => 'application/javascript; charset=UTF-8',
                    'json' => 'application/json; charset=UTF-8',
                    'svg' => 'image/svg+xml',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'woff' => 'font/woff',
                    'woff2' => 'font/woff2',
                ];
                $contentType = $map[strtolower((string) $ext)] ?? 'application/octet-stream';
            }
        }

        // StreamedResponse outputs directly in sendContent(). Capture it.
        if ((false === $body || '' === $body) && $response instanceof StreamedResponse) {
            $level = ob_get_level();
            ob_start();
            try {
                $response->sendContent();
                $body = ob_get_contents() ?: '';
            } finally {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }
        }

        $options = [
            'status' => $response->getStatusCode(),
            'headers' => $this->flattenForFulfill($this->stripContentLength($headers)),
        ];

        if ($contentType) {
            $options['contentType'] = $contentType;
        }

        if (is_string($body)) {
            if ($this->isBinaryContentType($contentType)) {
                $options['body'] = base64_encode($body);
                $options['isBase64'] = true;
            } else {
                $options['body'] = $body;
            }
        }

        return $options;
    }

    /**
     * Formats response headers for BrowserKit.
     *
     * Multiple values are joined with ", " except Set-Cookie, which is the only
     * header that cannot be comma-joined (cookie expiry dates contain commas and
     * each cookie must stay a separate header). Set-Cookie values are kept as a
     * list, which BrowserKit responses accept as-is.
     *
     * @param array<string, list<string|null>|string|null> $headers
     *
     * @return array<string, string|list<string>>
     */
    public function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $values) {
            if (is_array($values)) {
                $clean = array_values(array_filter($values, static fn (?string $v): bool => null !== $v));
                $formatted[$name] = 'set-cookie' === strtolower((string) $name) ? $clean : implode(', ', $clean);
            } else {
                $formatted[$name] = (string) $values;
            }
        }

        return $formatted;
    }

    /**
     * Flattens formatted headers into the dict shape expected by route.fulfill().
     *
     * Playwright's fulfill headers hold one string per name; the browser driver
     * splits a "\n"-joined Set-Cookie value back into separate headers.
     *
     * @param array<string, string|list<string>> $headers
     *
     * @return array<string, string>
     */
    private function flattenForFulfill(array $headers): array
    {
        $flattened = [];
        foreach ($headers as $name => $value) {
            $flattened[$name] = is_array($value) ? implode("\n", $value) : $value;
        }

        return $flattened;
    }

    /**
     * @param array<string, string|list<string>> $headers
     *
     * @return array<string, string|list<string>>
     */
    private function stripContentLength(array $headers): array
    {
        // Remove content-length as it may be stale when we construct body here
        foreach (['content-length', 'Content-Length'] as $k) {
            if (isset($headers[$k])) {
                unset($headers[$k]);
            }
        }

        return $headers;
    }

    public function isBinaryContentType(?string $contentType): bool
    {
        if (!$contentType) {
            return false;
        }

        $contentType = strtolower($contentType);

        if (str_starts_with($contentType, 'text/')) {
            return false;
        }

        $nonBinaryPrefixes = [
            'application/json',
            'application/javascript',
            'application/xml',
            'application/xhtml+xml',
            'application/x-www-form-urlencoded',
            'image/svg+xml',
        ];

        foreach ($nonBinaryPrefixes as $prefix) {
            if (str_starts_with($contentType, $prefix)) {
                return false;
            }
        }

        $baseType = trim(explode(';', $contentType, 2)[0]);

        if (str_ends_with($baseType, '+json') || str_ends_with($baseType, '+xml') || str_ends_with($baseType, '+html')) {
            return false;
        }

        return true;
    }
}

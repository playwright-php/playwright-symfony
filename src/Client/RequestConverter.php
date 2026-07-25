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

use Playwright\Network\RequestInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Converts Playwright requests to Symfony requests.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
class RequestConverter
{
    public function convertToSymfonyRequest(RequestInterface $playwrightRequest): SymfonyRequest
    {
        $url = parse_url($playwrightRequest->url());
        if (false === $url) {
            $url = [];
        }
        $method = $playwrightRequest->method();
        $headers = $playwrightRequest->headers();
        $postData = $playwrightRequest->postData();
        if (null === $postData) {
            $postData = $playwrightRequest->postDataBuffer();
        }

        $parameters = [];
        $cookies = [];
        $files = [];
        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => ($url['path'] ?? '/').(isset($url['query']) ? '?'.$url['query'] : ''),
            'SERVER_NAME' => $url['host'] ?? 'localhost',
            'SERVER_PORT' => $url['port'] ?? 80,
            'HTTP_HOST' => $url['host'] ?? 'localhost',
            'HTTPS' => ($url['scheme'] ?? 'http') === 'https' ? 'on' : 'off',
        ];

        $lower = array_change_key_case($headers, CASE_LOWER);
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', (string) $name));
            if ('CONTENT_TYPE' === $key || 'CONTENT_LENGTH' === $key || 'CONTENT_MD5' === $key) {
                $server[$key] = $value;
            } else {
                $server['HTTP_'.$key] = $value;
            }
        }

        if (isset($lower['cookie'])) {
            $cookies = $this->parseCookieHeader($lower['cookie']);
        }

        $content = null;
        if ($postData) {
            $contentType = $lower['content-type'] ?? '';
            if (str_starts_with(strtolower($contentType), 'application/x-www-form-urlencoded')) {
                parse_str($postData, $parameters);
                $content = $postData;
            } elseif (str_starts_with(strtolower($contentType), 'multipart/form-data')) {
                $content = $postData;
                $this->parseMultipartFormData($contentType, $postData, $parameters, $files);
            } else {
                $content = $postData;
            }
        }

        parse_str($url['query'] ?? '', $query);

        return new SymfonyRequest(
            $query,
            $parameters,
            [],
            $cookies,
            $files,
            $server,
            $content
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseCookieHeader(string $cookieHeader): array
    {
        $cookies = [];
        $cookiePairs = array_map('trim', explode(';', $cookieHeader));

        foreach ($cookiePairs as $pair) {
            if ('' === $pair) {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if ('' !== $name) {
                $cookies[$name] = urldecode($value);
            }
        }

        return $cookies;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $files
     */
    private function parseMultipartFormData(string $contentType, string $body, array &$parameters, array &$files): void
    {
        $boundary = $this->extractBoundary($contentType);
        if (null === $boundary) {
            return;
        }

        $delimiter = '--'.$boundary;
        $sections = explode($delimiter, $body);

        foreach ($sections as $section) {
            $section = ltrim($section, "\r\n");
            if ('' === $section) {
                continue;
            }

            $trimmed = trim($section);
            if ('--' === $trimmed) {
                // closing boundary
                continue;
            }

            $headerEnd = strpos($section, "\r\n\r\n");
            if (false === $headerEnd) {
                continue;
            }

            $rawHeaders = substr($section, 0, $headerEnd);
            $partBody = substr($section, $headerEnd + 4);

            $headers = $this->parsePartHeaders($rawHeaders);
            $disposition = $headers['content-disposition'] ?? null;
            if (null === $disposition) {
                continue;
            }

            $dispositionParts = $this->parseContentDisposition($disposition);
            $fieldName = $dispositionParts['name'] ?? null;
            if (!\is_string($fieldName) || '' === $fieldName) {
                continue;
            }

            $filename = $dispositionParts['filename'] ?? $dispositionParts['filename*'] ?? null;
            if (\is_string($filename) && str_contains($filename, "''")) {
                [$charset, $encoded] = explode("''", $filename, 2) + [null, null];
                $filename = null !== $encoded ? rawurldecode($encoded) : $filename;
            }

            $payload = rtrim($partBody, "\r\n");

            if (null !== $filename && '' !== $filename && is_string($filename)) {
                $this->createUploadedFile($fieldName, $filename, $payload, $headers, $files);

                continue;
            }

            $this->setArrayByPath($parameters, $fieldName, $payload);
        }
    }

    private function extractBoundary(string $contentType): ?string
    {
        $parts = HeaderUtils::split($contentType, ';=');
        if (empty($parts)) {
            return null;
        }

        array_shift($parts); // remove mime type
        $assoc = HeaderUtils::combine($parts);
        $boundary = $assoc['boundary'] ?? null;
        if (!\is_string($boundary) || '' === $boundary) {
            return null;
        }

        return HeaderUtils::unquote($boundary);
    }

    /**
     * @return array<mixed>
     */
    private function parseContentDisposition(string $header): array
    {
        $parts = HeaderUtils::split($header, ';=');
        if (empty($parts)) {
            return [];
        }

        $typePart = array_shift($parts);
        $assoc = HeaderUtils::combine($parts);

        foreach ($assoc as $key => $value) {
            if (\is_string($value)) {
                $assoc[$key] = HeaderUtils::unquote($value);
            }
        }

        if (!empty($typePart) && is_array($typePart) && isset($typePart[0])) {
            $firstPart = $typePart[0];
            if (is_string($firstPart)) {
                $assoc['type'] = strtolower($firstPart);
            } elseif (is_scalar($firstPart)) {
                $assoc['type'] = strtolower((string) $firstPart);
            }
        }

        /* @var array<string, mixed> */
        return $assoc;
    }

    /**
     * @return array<string, string>
     */
    private function parsePartHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines = preg_split('/\r?\n/', $rawHeaders);
        if (false === $lines) {
            return $headers;
        }
        foreach ($lines as $line) {
            if (false !== ($pos = strpos($line, ':'))) {
                $name = strtolower(trim(substr($line, 0, $pos)));
                $value = trim(substr($line, $pos + 1));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $files
     */
    private function createUploadedFile(string $name, string $filename, string $content, array $headers, array &$files): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pw_upload_');
        if (false === $tmp) {
            return;
        }

        file_put_contents($tmp, $content);
        $mime = $headers['content-type'] ?? null;
        $upload = new UploadedFile($tmp, $filename, is_string($mime) ? $mime : null, UPLOAD_ERR_OK, true);

        $this->setArrayByPath($files, $name, $upload);
    }

    /**
     * @param array<string, mixed> $target
     */
    private function setArrayByPath(array &$target, string $path, mixed $value): void
    {
        if (!str_contains($path, '[')) {
            $target[$path] = $value;

            return;
        }

        $segments = [];
        if (preg_match_all('/\[([^\]]*)\]/', $path, $matches)) {
            $bracketPos = strpos($path, '[');
            $root = false !== $bracketPos ? substr($path, 0, $bracketPos) : $path;
            $segments[] = $root;
            foreach ($matches[1] as $segment) {
                $segments[] = $segment;
            }
        } else {
            $target[$path] = $value;

            return;
        }

        $ref = &$target;
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if ('' === $segment || ctype_digit($segment)) {
                $segment = (int) $segment;
            }
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        if ('' === $last || ctype_digit($last)) {
            $last = (int) $last;
        }
        $ref[$last] = $value;
    }
}

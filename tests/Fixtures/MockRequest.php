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

namespace Playwright\Symfony\Tests\Fixtures;

use Playwright\Frame\FrameInterface;
use Playwright\Network\RequestInterface;
use Playwright\Network\ResponseInterface;

/**
 * Mock implementation of RequestInterface for testing purposes.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final readonly class MockRequest implements RequestInterface
{
    public function __construct(
        private string $url,
        private string $method = 'GET',
        private array $headers = [],
        private ?string $postData = null,
        private string $resourceType = 'document',
    ) {
    }

    public function url(): string
    {
        return $this->url;
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * Returns the request headers as an associative array.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        $result = [];
        foreach ($this->headers as $k => $v) {
            if (is_string($k) && (is_string($v) || is_numeric($v))) {
                $result[$k] = (string) $v;
            }
        }

        return $result;
    }

    public function postData(): ?string
    {
        return $this->postData;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function postDataJSON(): ?array
    {
        if (null === $this->postData) {
            return null;
        }

        if (function_exists('json_validate') && !json_validate($this->postData)) {
            return null;
        }

        try {
            $decoded = json_decode($this->postData, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function resourceType(): string
    {
        return $this->resourceType;
    }

    public function headerValue(string $name): ?string
    {
        $headers = $this->headers();
        $lower = strtolower($name);
        foreach ($headers as $k => $v) {
            if (strtolower($k) !== $lower) {
                continue;
            }
            $parts = array_map('trim', explode(',', $v));
            foreach ($parts as $part) {
                if ('' !== $part) {
                    return $part;
                }
            }

            return '';
        }

        return null;
    }

    public function headersArray(): array
    {
        $out = [];
        foreach ($this->headers() as $name => $value) {
            $parts = array_map('trim', explode(',', $value));
            foreach ($parts as $part) {
                if ('' === $part) {
                    continue;
                }
                $out[] = ['name' => $name, 'value' => $part];
            }
        }

        return $out;
    }

    public function allHeaders(): array
    {
        return $this->headers();
    }

    public function isNavigationRequest(): bool
    {
        return false;
    }

    public function postDataBuffer(): ?string
    {
        return $this->postData;
    }

    public function failure(): ?array
    {
        return null;
    }

    public function frame(): ?FrameInterface
    {
        return null;
    }

    public function redirectedFrom(): ?self
    {
        return null;
    }

    public function redirectedTo(): ?self
    {
        return null;
    }

    public function response(): ?ResponseInterface
    {
        return null;
    }

    public function serviceWorker(): mixed
    {
        return null;
    }

    public function sizes(): array
    {
        return [
            'requestBodySize' => 0,
            'requestHeadersSize' => 0,
            'responseBodySize' => 0,
            'responseHeadersSize' => 0,
        ];
    }

    public function timing(): array
    {
        return [
            'startTime' => -1.0,
            'domainLookupStart' => -1.0,
            'domainLookupEnd' => -1.0,
            'connectStart' => -1.0,
            'secureConnectionStart' => -1.0,
            'connectEnd' => -1.0,
            'requestStart' => -1.0,
            'responseStart' => -1.0,
            'responseEnd' => -1.0,
        ];
    }
}

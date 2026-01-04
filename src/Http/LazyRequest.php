<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP <https://github.com/playwright-php>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\Http;

use Playwright\Frame\FrameInterface;
use Playwright\Network\RequestInterface;
use Playwright\Network\ResponseInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
class LazyRequest implements RequestInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    public function url(): string
    {
        $url = is_callable($this->data['url'] ?? null) ? ($this->data['url'])() : ($this->data['url'] ?? '');

        return is_string($url) ? $url : '';
    }

    public function method(): string
    {
        $method = is_callable($this->data['method'] ?? null) ? ($this->data['method'])() : ($this->data['method'] ?? 'GET');

        return is_string($method) ? $method : 'GET';
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $raw = is_callable($this->data['headers'] ?? null) ? ($this->data['headers'])() : ($this->data['headers'] ?? []);
        if (!\is_array($raw)) {
            return [];
        }

        $headers = [];
        foreach ($raw as $k => $v) {
            if (\is_string($k) && (\is_string($v) || \is_numeric($v))) {
                $headers[$k] = (string) $v;
            }
        }

        return $headers;
    }

    public function postData(): ?string
    {
        $value = is_callable($this->data['postData'] ?? null) ? ($this->data['postData'])() : ($this->data['postData'] ?? null);

        return \is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function postDataJSON(): ?array
    {
        $postData = $this->postData();
        if (null === $postData) {
            return null;
        }

        if (\function_exists('json_validate') && !\json_validate($postData)) {
            return null;
        }

        try {
            $decoded = json_decode($postData, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($decoded)) {
            return null;
        }

        /* @var array<string, mixed> $decoded */
        return $decoded;
    }

    public function resourceType(): string
    {
        $type = $this->data['resourceType'] ?? 'document';

        return is_string($type) ? $type : 'document';
    }

    public function headerValue(string $name): ?string
    {
        $headers = $this->headers();
        $lowerName = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower($key) !== $lowerName) {
                continue;
            }
            $parts = array_map('trim', explode(',', $value));
            foreach ($parts as $part) {
                if ('' !== $part) {
                    return $part;
                }
            }

            return '';
        }

        return null;
    }

    /**
     * @return array<array{name: string, value: string}>
     */
    public function headersArray(): array
    {
        $result = [];
        foreach ($this->headers() as $name => $value) {
            $parts = array_map('trim', explode(',', $value));
            foreach ($parts as $part) {
                if ('' === $part) {
                    continue;
                }
                $result[] = ['name' => $name, 'value' => $part];
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function allHeaders(): array
    {
        return $this->headers();
    }

    public function isNavigationRequest(): bool
    {
        return (bool) ($this->data['isNavigationRequest'] ?? false);
    }

    public function postDataBuffer(): ?string
    {
        $buffer = $this->data['postDataBuffer'] ?? null;

        return \is_string($buffer) ? $buffer : null;
    }

    /**
     * @return array{errorText: string}|null
     */
    public function failure(): ?array
    {
        $failure = $this->data['failure'] ?? null;
        if (!\is_array($failure)) {
            return null;
        }

        $text = $failure['errorText'] ?? null;

        return \is_string($text) ? ['errorText' => $text] : null;
    }

    public function frame(): ?FrameInterface
    {
        return null;
    }

    public function redirectedFrom(): ?RequestInterface
    {
        return null;
    }

    public function redirectedTo(): ?RequestInterface
    {
        return null;
    }

    public function response(): ?ResponseInterface
    {
        return null;
    }

    public function serviceWorker(): mixed
    {
        return $this->data['serviceWorker'] ?? null;
    }

    /**
     * @return array{requestBodySize: int, requestHeadersSize: int, responseBodySize: int, responseHeadersSize: int}
     */
    public function sizes(): array
    {
        return [
            'requestBodySize' => 0,
            'requestHeadersSize' => 0,
            'responseBodySize' => 0,
            'responseHeadersSize' => 0,
        ];
    }

    /**
     * @return array{startTime: float, domainLookupStart: float, domainLookupEnd: float, connectStart: float, secureConnectionStart: float, connectEnd: float, requestStart: float, responseStart: float, responseEnd: float}
     */
    public function timing(): array
    {
        $timing = $this->data['timing'] ?? null;
        if (!\is_array($timing)) {
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

        $get = static fn (string $k): float => is_numeric($timing[$k] ?? null) ? (float) $timing[$k] : -1.0;

        return [
            'startTime' => $get('startTime'),
            'domainLookupStart' => $get('domainLookupStart'),
            'domainLookupEnd' => $get('domainLookupEnd'),
            'connectStart' => $get('connectStart'),
            'secureConnectionStart' => $get('secureConnectionStart'),
            'connectEnd' => $get('connectEnd'),
            'requestStart' => $get('requestStart'),
            'responseStart' => $get('responseStart'),
            'responseEnd' => $get('responseEnd'),
        ];
    }
}

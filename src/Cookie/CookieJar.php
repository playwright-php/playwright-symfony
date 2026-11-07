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

namespace Playwright\Symfony\Cookie;

use Playwright\Browser\BrowserContextInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar as BrowserKitCookieJar;
use Symfony\Component\BrowserKit\Response;

/**
 * A BrowserKit cookie jar backed by the browser context.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
final class CookieJar extends BrowserKitCookieJar
{
    private ?Response $browserResponse = null;

    /**
     * @param \Closure(): ?BrowserContextInterface $context
     * @param \Closure(): string                   $defaultDomain
     */
    public function __construct(
        private readonly \Closure $context,
        private readonly \Closure $defaultDomain,
    ) {
    }

    public function set(Cookie $cookie): void
    {
        $context = ($this->context)() ?? throw new \RuntimeException('Browser context is null - browser may not be started');

        // Passing a URL would make Playwright derive the path from it.
        $data = [
            'name' => $cookie->getName(),
            'value' => $cookie->getRawValue(),
            'domain' => $cookie->getDomain() ?: ($this->defaultDomain)(),
            'path' => $cookie->getPath(),
            'secure' => $cookie->isSecure(),
            'httpOnly' => $cookie->isHttpOnly(),
        ];

        if (null !== $expires = $cookie->getExpiresTime()) {
            $data['expires'] = (int) $expires;
        }

        $sameSite = match (strtolower((string) $cookie->getSameSite())) {
            'strict' => 'Strict',
            'lax' => 'Lax',
            'none' => 'None',
            default => null,
        };

        if (null !== $sameSite) {
            $data['sameSite'] = $sameSite;
        }

        $context->addCookies([$data]);
    }

    public function get(string $name, string $path = '/', ?string $domain = null): ?Cookie
    {
        foreach ($this->all() as $cookie) {
            if ($name !== $cookie->getName() || !str_starts_with($path, $cookie->getPath())) {
                continue;
            }

            if (!$domain || '' === $cookie->getDomain() || str_ends_with('.'.$domain, '.'.ltrim($cookie->getDomain(), '.'))) {
                return $cookie;
            }
        }

        return null;
    }

    public function expire(string $name, ?string $path = '/', ?string $domain = null): void
    {
        $filters = ['name' => $name, 'path' => $path ?? '/'];

        if ($domain) {
            $filters['domain'] = $domain;
        }

        // Playwright PHP forwards a flat filter object to the browser.
        ($this->context)()?->clearCookies($filters); // @phpstan-ignore argument.type
    }

    public function clear(): void
    {
        ($this->context)()?->clearCookies();
    }

    /**
     * BrowserKit replays Set-Cookie after navigation, even when the browser already handled it.
     */
    public function skipResponseCookies(Response $response): void
    {
        $this->browserResponse = $response;
    }

    public function updateFromResponse(Response $response, ?string $uri = null): void
    {
        if ($response === $this->browserResponse) {
            $this->browserResponse = null;

            return;
        }

        parent::updateFromResponse($response, $uri);
    }

    /**
     * @return Cookie[]
     */
    public function all(): array
    {
        return array_map(self::toBrowserKitCookie(...), ($this->context)()?->cookies() ?? []);
    }

    /**
     * @return array<string, string>
     */
    public function allValues(string $uri, bool $returnsRawValue = false): array
    {
        $values = [];

        foreach (($this->context)()?->cookies([$uri]) ?? [] as $cookie) {
            $values[$cookie['name']] = $returnsRawValue ? $cookie['value'] : urldecode($cookie['value']);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $cookie
     */
    private static function toBrowserKitCookie(array $cookie): Cookie
    {
        $sameSite = self::toString($cookie['sameSite'] ?? null);

        return new Cookie(
            name: self::toString($cookie['name'] ?? null),
            value: self::toString($cookie['value'] ?? null),
            expires: self::normalizeExpires($cookie['expires'] ?? null),
            path: self::toString($cookie['path'] ?? null) ?: '/',
            domain: self::toString($cookie['domain'] ?? null),
            secure: (bool) ($cookie['secure'] ?? false),
            httponly: (bool) ($cookie['httpOnly'] ?? false),
            encodedValue: true,
            samesite: '' === $sameSite ? null : strtolower($sameSite),
        );
    }

    /**
     * Playwright returns -1 for session cookies and can return fractional timestamps.
     */
    private static function normalizeExpires(mixed $expires): ?string
    {
        if (!is_numeric($expires)) {
            return null;
        }

        $timestamp = (int) $expires;

        return $timestamp < 0 ? null : (string) $timestamp;
    }

    private static function toString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

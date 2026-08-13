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

/**
 * A BrowserKit cookie jar backed by the browser context.
 *
 * The real browser owns the cookies, so a jar holding its own copy is stale the moment javascript
 * or a response changes one, and writing to it has no effect on what the browser sends. Every read
 * and write here goes to the context instead, in the same spirit as
 * {@see \Symfony\Component\Panther\Cookie\CookieJar}.
 *
 * updateFromSetCookie(), updateFromResponse() and flushExpiredCookies() are inherited: the first
 * two funnel into set(), and the browser expires its own cookies.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
final class CookieJar extends BrowserKitCookieJar
{
    /**
     * @param \Closure(): string $defaultDomain the domain to scope cookies set without one, usually
     *                                          the host currently being browsed
     */
    public function __construct(
        private readonly BrowserContextInterface $context,
        private readonly \Closure $defaultDomain,
    ) {
    }

    public function set(Cookie $cookie): void
    {
        // playwright needs a domain/path pair: passing a url instead makes it derive the path from
        // that url, which silently widens a cookie scoped to something narrower
        $data = [
            'name' => $cookie->getName(),
            'value' => $cookie->getValue(),
            'domain' => $cookie->getDomain() ?: ($this->defaultDomain)(),
            'path' => $cookie->getPath(),
            'secure' => $cookie->isSecure(),
            'httpOnly' => $cookie->isHttpOnly(),
        ];

        if (null !== $expires = $cookie->getExpiresTime()) {
            $data['expires'] = (int) $expires;
        }

        // playwright only accepts these three, spelled exactly like this
        $sameSite = match (strtolower((string) $cookie->getSameSite())) {
            'strict' => 'Strict',
            'lax' => 'Lax',
            'none' => 'None',
            default => null,
        };

        if (null !== $sameSite) {
            $data['sameSite'] = $sameSite;
        }

        $this->context->addCookies([$data]);
    }

    public function get(string $name, string $path = '/', ?string $domain = null): ?Cookie
    {
        foreach ($this->all() as $cookie) {
            if ($name !== $cookie->getName() || !str_starts_with($path, $cookie->getPath())) {
                continue;
            }

            if (null === $domain || '' === $cookie->getDomain() || str_ends_with('.'.$domain, '.'.ltrim($cookie->getDomain(), '.'))) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * Unlike BrowserKit's jar, which is keyed by domain and path, this removes the cookie from
     * every domain and path it is stored under.
     */
    public function expire(string $name, ?string $path = '/', ?string $domain = null): void
    {
        $this->context->deleteCookie($name);
    }

    public function clear(): void
    {
        $this->context->clearCookies();
    }

    /**
     * @return Cookie[]
     */
    public function all(): array
    {
        return array_map(self::toBrowserKitCookie(...), $this->context->cookies());
    }

    /**
     * @return array<string, string>
     */
    public function allValues(string $uri, bool $returnsRawValue = false): array
    {
        $values = [];

        // the browser does the matching the parent does by hand: domain, path, secure and expiry
        foreach ($this->context->cookies([$uri]) as $cookie) {
            $cookie = self::toBrowserKitCookie($cookie);

            $values[$cookie->getName()] = $returnsRawValue ? $cookie->getRawValue() : $cookie->getValue();
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    public function allRawValues(string $uri): array
    {
        return $this->allValues($uri, true);
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
            // playwright capitalizes it, BrowserKit round-trips whatever it was given
            samesite: '' === $sameSite ? null : strtolower($sameSite),
        );
    }

    /**
     * Playwright reports "expires" as a number: -1 for session cookies, a Unix timestamp
     * (possibly float) otherwise. BrowserKit only accepts an int since 8.1; before that the
     * parameter is ?string, parsed with createFromFormat('U'), so a numeric string is what
     * satisfies every supported version. Past timestamps count as expired, so negatives must
     * map to null.
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

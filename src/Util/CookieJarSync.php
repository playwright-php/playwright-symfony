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

namespace Playwright\Symfony\Util;

use Playwright\Browser\BrowserContextInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;

/**
 * Copies cookies from a Playwright browser context into a BrowserKit cookie jar.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final class CookieJarSync
{
    /**
     * Seeds the jar with every cookie currently stored in the browser context.
     */
    public static function fromContext(CookieJar $jar, BrowserContextInterface $context): void
    {
        foreach ($context->cookies() as $cookie) {
            $jar->set(self::toBrowserKitCookie($cookie));
        }
    }

    /**
     * Seeds the jar with the context cookies that match the given URL.
     */
    public static function toJarFromUrl(CookieJar $jar, BrowserContextInterface $context, string $url): void
    {
        foreach ($context->cookies([$url]) as $cookie) {
            $jar->set(self::toBrowserKitCookie($cookie));
        }
    }

    /**
     * @param array<string, mixed> $cookie
     */
    private static function toBrowserKitCookie(array $cookie): Cookie
    {
        return new Cookie(
            name: self::toString($cookie['name'] ?? ''),
            value: self::toString($cookie['value'] ?? ''),
            expires: self::normalizeExpires($cookie['expires'] ?? null),
            path: self::toString($cookie['path'] ?? '/'),
            domain: self::toString($cookie['domain'] ?? ''),
            secure: (bool) ($cookie['secure'] ?? false),
            httponly: (bool) ($cookie['httpOnly'] ?? false),
        );
    }

    /**
     * Playwright reports "expires" as a number: -1 for session cookies, a Unix
     * timestamp (possibly float) otherwise. BrowserKit only accepts an int since
     * 8.1; before that the parameter is ?string, parsed with createFromFormat('U'),
     * so a numeric string is what satisfies every supported version. Past
     * timestamps count as expired, so negatives must map to null.
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

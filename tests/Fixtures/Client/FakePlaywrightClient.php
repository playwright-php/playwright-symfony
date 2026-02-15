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

namespace Playwright\Symfony\Tests\Fixtures\Client;

use Playwright\Page\PageInterface;
use Playwright\Symfony\Client\PlaywrightClient;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class FakePlaywrightClient extends PlaywrightClient
{
    /** @var array<string, array<int, mixed>> */
    public array $calls = [];

    public ?SymfonyRequest $lastRequest = null;
    public ?SymfonyResponse $lastResponse = null;

    public function __construct()
    {
        // Do not call parent constructor; this fake only records calls.
    }

    public function setCookie(string $name, string $value, array $options = []): void
    {
        $this->calls['setCookie'][] = [$name, $value, $options];
    }

    public function getCookie(string $name, ?string $url = null): ?string
    {
        $this->calls['getCookie'][] = [$name, $url];

        return null;
    }

    public function clearCookies(): void
    {
        $this->calls['clearCookies'][] = true;
    }

    public function clearCookie(string $name, ?string $domain = null, string $path = '/'): void
    {
        $this->calls['clearCookie'][] = [$name, $domain, $path];
    }

    public function authenticate(string $identifier = 'user', array $context = []): void
    {
        $this->calls['authenticate'][] = [$identifier, $context];
    }

    public function logout(): void
    {
        $this->calls['logout'][] = true;
    }

    public function getLastSymfonyRequest(): ?SymfonyRequest
    {
        $this->calls['getLastSymfonyRequest'][] = true;

        return $this->lastRequest;
    }

    public function getLastSymfonyResponse(): ?SymfonyResponse
    {
        $this->calls['getLastSymfonyResponse'][] = true;

        return $this->lastResponse;
    }

    public function visit(string $path): PageInterface
    {
        $this->calls['visit'][] = $path;

        throw new \BadMethodCallException('Not implemented in FakePlaywrightClient');
    }

    public function getPage(): PageInterface
    {
        $this->calls['getPage'][] = true;

        throw new \BadMethodCallException('Not implemented in FakePlaywrightClient');
    }
}

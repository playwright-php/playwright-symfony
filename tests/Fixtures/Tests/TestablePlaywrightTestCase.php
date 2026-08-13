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

namespace Playwright\Symfony\Tests\Fixtures\Tests;

use Playwright\Symfony\Client\BrowserRegistry;
use Playwright\Symfony\Client\PlaywrightKernelClient;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class TestablePlaywrightTestCase extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function setTestClient(?PlaywrightKernelClient $client): void
    {
        self::$playwrightClient = $client;
    }

    public function setTestBrowser(BrowserRegistry $browser): void
    {
        self::$sharedBrowser = $browser;
    }

    public static function setSharedBrowser(?BrowserRegistry $browser): void
    {
        self::$sharedBrowser = $browser;
    }

    public function callTearDown(): void
    {
        $this->tearDown();
    }

    public function callSetUp(): void
    {
        $this->setUp();
    }

    public static function publicGetPlaywrightClient(): PlaywrightKernelClient
    {
        return self::getPlaywrightClient();
    }

    public static function publicCreatePlaywrightClient(): PlaywrightKernelClient
    {
        return self::createPlaywrightClient();
    }

    public static function isKernelBooted(): bool
    {
        return self::$booted;
    }

    public function publicSetCookie(string $name, string $value, array $options = []): void
    {
        $this->setCookie($name, $value, $options);
    }

    public function publicGetCookie(string $name, ?string $url = null): ?string
    {
        return $this->getCookie($name, $url);
    }

    public function publicClearCookies(): void
    {
        $this->clearCookies();
    }

    public function publicClearCookie(string $name, ?string $domain = null, string $path = '/'): void
    {
        $this->clearCookie($name, $domain, $path);
    }

    public function publicAuthenticate(string $identifier = 'user', array $context = []): void
    {
        $this->authenticate($identifier, $context);
    }

    public function publicLogout(string $firewallContext = 'main'): static
    {
        return $this->logout($firewallContext);
    }

    /**
     * @param array<string, mixed> $tokenAttributes
     */
    public function publicLoginUser(object $user, string $firewallContext = 'main', array $tokenAttributes = []): static
    {
        return $this->loginUser($user, $firewallContext, $tokenAttributes);
    }

    public function publicGetLastRequest(): ?SymfonyRequest
    {
        return $this->getLastRequest();
    }

    public function publicGetLastResponse(): ?SymfonyResponse
    {
        return $this->getLastResponse();
    }

    public function publicLoadFixtures(array $fixtures): void
    {
        $this->loadFixtures($fixtures);
    }

    public function publicBeforeRequest(SymfonyRequest $request): void
    {
        $this->beforeRequest($request);
    }

    public function publicAfterResponse(SymfonyResponse $response): void
    {
        $this->afterResponse($response);
    }

    public function publicGetBaseUrl(): string
    {
        return $this->getBaseUrl();
    }
}

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

use Playwright\Network\RequestInterface;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ConcretePlaywrightTestCase extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    // Public wrappers for testing methods that are now in components
    public function publicConvertToSymfonyRequest(RequestInterface $request): SymfonyRequest
    {
        $converter = new RequestConverter();

        return $converter->convertToSymfonyRequest($request);
    }

    public function publicFormatHeaders(array $headers): array
    {
        $converter = new ResponseConverter();

        return $converter->formatHeaders($headers);
    }

    public function publicShouldInterceptRequest(array $url): bool
    {
        $interceptedHosts = ['localhost', '127.0.0.1', 'testapp.local'];

        return isset($url['host']) && in_array($url['host'], $interceptedHosts, true);
    }

    public function publicGetBaseUrl(): string
    {
        return $this->getBaseUrl();
    }

    public function publicIsHeadless(): bool
    {
        return 'false' !== getenv('PLAYWRIGHT_HEADLESS');
    }

    public function publicIsBinaryContentType(?string $contentType = null): bool
    {
        $converter = new ResponseConverter();

        return $converter->isBinaryContentType($contentType);
    }

    public function publicPrepareFulfillOptions(SymfonyResponse $response): array
    {
        $converter = new ResponseConverter();

        return $converter->prepareFulfillOptions($response);
    }

    public function setInterceptedHosts(array $hosts): void
    {
        // Request conversion tests do not create a browser client.
    }
}

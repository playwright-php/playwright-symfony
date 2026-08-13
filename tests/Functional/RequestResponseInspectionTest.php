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

namespace Playwright\Symfony\Tests\Functional;

use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\KernelInterface;

final class RequestResponseInspectionTest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testGetLastRequestReturnsSymfonyRequest(): void
    {
        $this->visit('/hello');

        $request = $this->getLastRequest();

        self::assertInstanceOf(SymfonyRequest::class, $request);
        self::assertSame('GET', $request->getMethod());
        self::assertStringContainsString('/hello', $request->getRequestUri());
    }

    public function testGetLastRequestHasAccessibleHeaders(): void
    {
        $this->visit('/hello');

        $request = $this->getLastRequest();

        self::assertNotNull($request);
        self::assertNotEmpty($request->headers->all());
        self::assertTrue($request->headers->has('host'));
    }

    public function testGetLastRequestWithQueryParameters(): void
    {
        $this->visit('/echo?foo=bar&page=2');

        $request = $this->getLastRequest();

        self::assertNotNull($request);
        self::assertSame('bar', $request->query->get('foo'));
        self::assertSame('2', $request->query->get('page'));
    }

    public function testGetLastRequestWithPostData(): void
    {
        $this->visit('/form');

        // Submit form with POST data
        $this->getPage()->locator('#name')->fill('Test User');
        $this->getPage()->locator('button[type="submit"]')->click();

        $request = $this->getLastRequest();

        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('Test User', $request->request->get('name'));
    }

    public function testMultipleRequestsTrackedCorrectly(): void
    {
        // First request
        $this->visit('/hello');
        $request1 = $this->getLastRequest();
        self::assertStringContainsString('/hello', $request1->getRequestUri());

        // Second request - should replace first
        $this->visit('/form');
        $request2 = $this->getLastRequest();
        self::assertStringContainsString('/form', $request2->getRequestUri());
        self::assertNotEquals($request1->getRequestUri(), $request2->getRequestUri());
    }

    public function testGetLastResponseReturnsSymfonyResponse(): void
    {
        $this->visit('/hello');

        $response = $this->getLastResponse();

        self::assertInstanceOf(SymfonyResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testGetLastResponseHasAccessibleContent(): void
    {
        $this->visit('/hello');

        $response = $this->getLastResponse();

        self::assertNotNull($response);
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('hello from app', $content);
    }

    public function testGetLastResponseHasAccessibleHeaders(): void
    {
        $this->visit('/hello');

        $response = $this->getLastResponse();

        self::assertNotNull($response);
        self::assertNotEmpty($response->headers->all());
    }

    public function testGetLastResponseStatusCodeForDifferentResponses(): void
    {
        // Success response
        $this->visit('/hello');
        $response1 = $this->getLastResponse();
        self::assertSame(200, $response1->getStatusCode());

        // Validation error response
        $this->visit('/form');
        $this->getPage()->evaluate('() => { document.querySelector("#name").removeAttribute("required"); }');
        $this->getPage()->locator('#name')->fill('');
        $this->getPage()->locator('button[type="submit"]')->click();

        $response2 = $this->getLastResponse();
        self::assertSame(400, $response2->getStatusCode());
    }

    public function testRequestAndResponseAreLinked(): void
    {
        $this->visit('/hello');

        $request = $this->getLastRequest();
        $response = $this->getLastResponse();

        self::assertNotNull($request);
        self::assertNotNull($response);

        // They should be from the same request/response cycle
        self::assertStringContainsString('/hello', $request->getRequestUri());
        self::assertStringContainsString('hello from app', $response->getContent());
    }
}

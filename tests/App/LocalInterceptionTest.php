<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\App;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

final class LocalInterceptionTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testKernelHandlesBasicRequest(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        $request = Request::create('http://localhost/hello', 'GET');
        $response = $kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('hello from app', $response->getContent());

        $kernel->shutdown();
    }

    #[RunInSeparateProcess]
    public function testKernelHandlesRedirectResponse(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        $request = Request::create('http://localhost/redirect', 'GET');
        $response = $kernel->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/hello', $response->headers->get('location'));

        $kernel->shutdown();
    }

    #[RunInSeparateProcess]
    public function testKernelHandlesBinaryResponse(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        $request = Request::create('http://localhost/binary', 'GET');
        $response = $kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->headers->get('content-type'));
        $this->assertStringStartsWith("\x89PNG\x0D\x0A\x1A\x0A", $response->getContent());
        $this->assertGreaterThan(1024, strlen($response->getContent() ?? ''));

        $kernel->shutdown();
    }

    #[RunInSeparateProcess]
    public function testKernelHandlesLargeBodyResponse(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        $request = Request::create('http://localhost/big', 'GET');
        $response = $kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('content-type'));
        $content = $response->getContent();
        $this->assertGreaterThanOrEqual(4 * 1024 * 1024, strlen($content ?? '')); // ~4MB
        $this->assertStringContainsString('0123456789abcdef', $content ?? '');

        $kernel->shutdown();
    }
}

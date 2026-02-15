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

    #[RunInSeparateProcess]
    public function testAssetMapperDevServerServesCss(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        $assetUrl = $kernel->getContainer()->get('test.service_container')->get('assets.packages')->getUrl('styles/app.css');

        $request = Request::create('http://localhost'.$assetUrl, 'GET');
        $response = $kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/css', $response->headers->get('content-type'));
        $content = $response->getContent();
        if (false === $content) {
            $level = ob_get_level();
            ob_start();
            try {
                $response->sendContent();
                $content = ob_get_contents() ?: '';
            } finally {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }
        }
        $this->assertIsString($content);
        $this->assertStringContainsString('.styled-box', $content);
        $this->assertStringContainsString('#3498db', $content);

        $kernel->shutdown();
    }
}

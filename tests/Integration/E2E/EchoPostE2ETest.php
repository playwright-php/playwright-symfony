<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Integration\E2E;

use Playwright\Symfony\Test\Assert\PlaywrightTestAssertionsTrait;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class EchoPostE2ETest extends PlaywrightTestCase
{
    use PlaywrightTestAssertionsTrait;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false); // debug=false to reduce log noise
    }

    public function testPostJsonEchoesBodyAndHeaders(): void
    {
        // Set origin to http://localhost first to avoid CORS preflight
        $this->visit('/hello');

        $result = $this->page->evaluate(<<<'JS'
            async () => {
              const diagnostics = {
                currentUrl: window.location.href,
                fetchAttempted: false,
                fetchResponse: null,
                error: null
              };
              
              try {
                diagnostics.fetchAttempted = true;
                
                // Try the fetch request
                const res = await fetch('/echo', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-Test': 'abc'
                  },
                  body: JSON.stringify({ msg: 'hi' })
                });
                
                diagnostics.fetchResponse = {
                  status: res.status,
                  statusText: res.statusText,
                  url: res.url,
                  ok: res.ok
                };
                
                if (res.ok) {
                  const json = await res.json();
                  return json;
                } else {
                  return { diagnostics, error: 'HTTP error: ' + res.status };
                }
              } catch (error) {
                diagnostics.error = {
                  message: error.message,
                  name: error.name
                };
                return { diagnostics, error: 'Fetch exception: ' + error.message };
              }
            }
        JS);

        $this->assertSame('POST', $result['method'] ?? null);
        $this->assertSame('/echo', $result['path'] ?? null);
        $this->assertSame('abc', $result['headers']['x-test'] ?? null);
        $this->assertSame('application/json', strtok((string) ($result['headers']['content-type'] ?? ''), ';'));
        $this->assertSame('hi', $result['body']['msg'] ?? null);
    }
}

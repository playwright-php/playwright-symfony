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

namespace Playwright\Symfony\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Playwright\Browser\BrowserInterface;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Client\BrowserRegistry;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\Tests\TestablePlaywrightTestCase;

/**
 * Runs the lifecycle of several test classes against a real browser.
 */
#[CoversClass(PlaywrightTestCase::class)]
#[CoversClass(BrowserRegistry::class)]
final class BrowserReuseTest extends TestCase
{
    private ?string $reuseBrowser = null;

    protected function setUp(): void
    {
        // $_ENV is read first, so the tests do not depend on the variable of the calling shell
        $this->reuseBrowser = $_ENV['PLAYWRIGHT_REUSE_BROWSER'] ?? null;
        $_ENV['PLAYWRIGHT_REUSE_BROWSER'] = 'true';
    }

    protected function tearDown(): void
    {
        // each test starts without a browser, so one failure does not reach the next test
        ReusedBrowserTestCase::stopBrowser();

        if (null === $this->reuseBrowser) {
            unset($_ENV['PLAYWRIGHT_REUSE_BROWSER']);
        } else {
            $_ENV['PLAYWRIGHT_REUSE_BROWSER'] = $this->reuseBrowser;
        }
    }

    public function testBrowserOutlivesItsTestClass(): void
    {
        $first = $this->runClass(static function (ReusedBrowserTestCase $test): void {
            self::assertStringContainsString('hello from app', (string) $test->open('/hello')->content());
        });

        $browser = self::browserOf($first);

        $second = $this->runClass(static function (ReusedBrowserTestCase $test): void {
            self::assertStringContainsString('hello from app', (string) $test->open('/hello')->content());
        });

        self::assertSame($first, $second);
        self::assertSame($browser, self::browserOf($first), 'The second class should run on the browser of the first');
    }

    public function testBrowserStopsAfterEachClassWhenReuseIsOff(): void
    {
        $_ENV['PLAYWRIGHT_REUSE_BROWSER'] = 'false';

        $browser = $this->runClass(static function (ReusedBrowserTestCase $test): void {
            $test->open('/hello');
        });

        self::assertNull($browser, 'The browser should be stopped and forgotten after the class');
    }

    public function testRouteHandlerFailureDoesNotReachTheNextClass(): void
    {
        $handled = false;

        $this->runClass(static function (ReusedBrowserTestCase $test) use (&$handled): void {
            $page = $test->open('/hello');
            $page->route('**/late-failure', static function () use (&$handled): never {
                $handled = true;

                throw new \RuntimeException('Raised after the test body.');
            });
            $page->evaluate('() => { setTimeout(() => fetch("/late-failure").catch(() => {}), 50); }');

            // leave the route event unread, as a test that ends before its page settles does
            usleep(300_000);
        });
        self::assertTrue($handled, 'The route handler should have run when the sessions closed');

        $this->runClass(static function (ReusedBrowserTestCase $test): void {
            self::assertStringContainsString('hello from app', (string) $test->open('/hello')->content());
        });
    }

    #[RequiresPhpExtension('posix')]
    #[RequiresPhpExtension('pcntl')]
    public function testBrowserIsReplacedWhenItsPlaywrightProcessIsGone(): void
    {
        $registry = $this->runClass(static function (ReusedBrowserTestCase $test): void {
            $test->open('/hello');
        });
        $lost = self::browserOf($registry);

        self::killTheOnly(self::playwrightProcesses()['server']);

        $this->runClass(static function (ReusedBrowserTestCase $test): void {
            self::assertStringContainsString('hello from app', (string) $test->open('/hello')->content());
        });

        self::assertNotSame($lost, self::browserOf($registry));
    }

    public function testRouteHandlerFailureReachesTheTestAndKeepsTheBrowser(): void
    {
        $browser = null;
        $failure = null;

        $registry = $this->runClass(static function (ReusedBrowserTestCase $test) use (&$browser, &$failure): void {
            $page = $test->open('/hello');
            $browser = self::browserOf(ReusedBrowserTestCase::sharedBrowser());
            $page->route('**/late-failure', static function (): never {
                throw new \LogicException('Raised by the application.');
            });
            $page->evaluate('() => { setTimeout(() => fetch("/late-failure").catch(() => {}), 50); }');
            usleep(300_000);

            try {
                ReusedBrowserTestCase::publicCreatePlaywrightClient();
            } catch (\LogicException $failure) {
            }
        });

        self::assertInstanceOf(\LogicException::class, $failure, 'The failure should reach the test that opens the next client');
        self::assertNotNull($registry);
        self::assertSame($browser, self::browserOf($registry), 'The browser should not be replaced');
    }

    #[DataProvider('warningHandlers')]
    #[RequiresPhpExtension('posix')]
    #[RequiresPhpExtension('pcntl')]
    public function testCrashedBrowserIsReplaced(bool $warningsAreThrown): void
    {
        if ('chromium' !== BrowserRegistry::fromEnvironment()->getBrowserType()) {
            self::markTestSkipped('Finds the browser process of Chromium only.');
        }

        $crashed = $this->runClass(static function (ReusedBrowserTestCase $test): void {
            $test->open('/hello');
        });
        $browser = self::browserOf($crashed);

        self::killTheOnly(self::playwrightProcesses()['browser']);
        self::assertCount(1, self::playwrightProcesses()['server'], 'The Playwright process should survive its browser');

        if ($warningsAreThrown) {
            set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }, \E_WARNING);
        }

        try {
            $this->runClass(static function (ReusedBrowserTestCase $test): void {
                self::assertStringContainsString('hello from app', (string) $test->open('/hello')->content());
            });
        } finally {
            if ($warningsAreThrown) {
                restore_error_handler();
            }
        }

        self::assertNotSame($browser, self::browserOf($crashed));
    }

    /**
     * The base package raises a PHP warning when the browser is gone. The error handler of a
     * Symfony debug kernel booted outside a test throws it, PHPUnit's own handler records it.
     *
     * @return iterable<string, array{bool}>
     */
    public static function warningHandlers(): iterable
    {
        yield 'warnings recorded' => [false];
        yield 'warnings thrown' => [true];
    }

    private static function browserOf(?BrowserRegistry $registry): ?BrowserInterface
    {
        self::assertNotNull($registry);
        $browser = (new \ReflectionProperty(BrowserRegistry::class, 'browser'))->getValue($registry);
        self::assertTrue(null === $browser || $browser instanceof BrowserInterface);

        return $browser;
    }

    /**
     * @param list<int> $pids
     */
    private static function killTheOnly(array $pids): void
    {
        self::assertCount(1, $pids, 'Exactly one such process should belong to this PHP process');
        posix_kill($pids[0], \SIGKILL);
        usleep(300_000);
    }

    /**
     * Finds the Playwright server started by this PHP process, and the browser it launched.
     *
     * @return array{server: list<int>, browser: list<int>}
     */
    private static function playwrightProcesses(): array
    {
        $parents = [];
        $commands = [];
        // ww: Linux cuts the command at the terminal width otherwise
        foreach (explode("\n", trim((string) shell_exec('ps -axwwo pid=,ppid=,command='))) as $line) {
            if (1 === preg_match('/^\s*(\d+)\s+(\d+)\s+(.*)$/', $line, $matches)) {
                $parents[(int) $matches[1]] = (int) $matches[2];
                $commands[(int) $matches[1]] = $matches[3];
            }
        }

        if ([] === $commands) {
            self::markTestSkipped('Needs ps to find the Playwright processes.');
        }

        $servers = [];
        foreach ($commands as $pid => $command) {
            if (!str_contains($command, 'playwright-server.js')) {
                continue;
            }

            for ($ancestor = $parents[$pid] ?? 0; $ancestor > 1; $ancestor = $parents[$ancestor] ?? 0) {
                if (getmypid() === $ancestor) {
                    $servers[] = $pid;

                    break;
                }
            }
        }

        // the browser is the child of the server that carries no --type, unlike the renderer and
        // GPU processes
        $browsers = [];
        foreach ($commands as $pid => $command) {
            if (in_array($parents[$pid] ?? 0, $servers, true) && !str_contains($command, '--type=')) {
                $browsers[] = $pid;
            }
        }

        return ['server' => $servers, 'browser' => $browsers];
    }

    /**
     * Runs one test of a class, then the end of that class, and returns the browser it leaves behind.
     *
     * @param \Closure(ReusedBrowserTestCase): void $body
     */
    private function runClass(\Closure $body): ?BrowserRegistry
    {
        $test = new ReusedBrowserTestCase('testPlaceholder');
        $test->callSetUp();

        try {
            $body($test);
        } finally {
            $test->callTearDown();
            ReusedBrowserTestCase::tearDownAfterClass();
        }

        return ReusedBrowserTestCase::sharedBrowser();
    }
}

final class ReusedBrowserTestCase extends TestablePlaywrightTestCase
{
    /**
     * The name every instance is built with: PHPUnit looks the method up when the code under
     * test raises a PHP warning.
     */
    public function testPlaceholder(): void
    {
    }

    public static function sharedBrowser(): ?BrowserRegistry
    {
        return self::$sharedBrowser;
    }

    public function open(string $path): PageInterface
    {
        return static::getPlaywrightClient()->visit($path);
    }
}

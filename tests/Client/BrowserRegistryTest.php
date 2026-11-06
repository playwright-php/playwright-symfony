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

namespace Playwright\Symfony\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Browser\BrowserInterface;
use Playwright\PlaywrightClient;
use Playwright\Symfony\Client\BrowserRegistry;
use Playwright\Symfony\Client\BrowserSession;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyBrowserContext;
use Playwright\Symfony\Tests\Fixtures\Browser\DummyPage;

#[CoversClass(BrowserRegistry::class)]
#[UsesClass(BrowserSession::class)]
class BrowserRegistryTest extends TestCase
{
    /** @var array<string, array{string|false, mixed, mixed}> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        foreach (['PLAYWRIGHT_BROWSER', 'PLAYWRIGHT_HEADLESS', 'PLAYWRIGHT_CHANNEL', 'PW_CHANNEL'] as $name) {
            $this->originalEnvironment[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $name => $values) {
            $this->restoreEnvironmentVariable($name, $values);
        }
    }

    public function testFromEnvironmentDefaultsToChromiumOnInvalidBrowser(): void
    {
        putenv('PLAYWRIGHT_BROWSER=invalid');
        putenv('PLAYWRIGHT_HEADLESS=1');

        $browser = BrowserRegistry::fromEnvironment();

        $this->assertSame('chromium', $browser->getBrowserType());
        $this->assertTrue($browser->isHeadless());
    }

    public function testFromEnvironmentRespectsValidBrowserTypesAndHeadlessFlag(): void
    {
        putenv('PLAYWRIGHT_BROWSER=firefox');
        putenv('PLAYWRIGHT_HEADLESS=false');

        $browser = BrowserRegistry::fromEnvironment();

        $this->assertSame('firefox', $browser->getBrowserType());
        $this->assertFalse($browser->isHeadless());

        putenv('PLAYWRIGHT_BROWSER=webkit');
        putenv('PLAYWRIGHT_HEADLESS=1');

        $browser = BrowserRegistry::fromEnvironment();

        $this->assertSame('webkit', $browser->getBrowserType());
        $this->assertTrue($browser->isHeadless());
    }

    #[DataProvider('channelEnvironmentSources')]
    public function testFromEnvironmentRespectsChannel(string $name, string $source): void
    {
        match ($source) {
            'env' => $_ENV[$name] = 'chrome',
            'server' => $_SERVER[$name] = 'chrome',
            'process' => putenv($name.'=chrome'),
        };

        $browser = BrowserRegistry::fromEnvironment();

        $this->assertTrue($browser->equals(new BrowserRegistry(launchOptions: ['channel' => 'chrome'])));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function channelEnvironmentSources(): iterable
    {
        yield 'PLAYWRIGHT_CHANNEL in ENV' => ['PLAYWRIGHT_CHANNEL', 'env'];
        yield 'PLAYWRIGHT_CHANNEL in SERVER' => ['PLAYWRIGHT_CHANNEL', 'server'];
        yield 'PLAYWRIGHT_CHANNEL in process' => ['PLAYWRIGHT_CHANNEL', 'process'];
        yield 'PW_CHANNEL in ENV' => ['PW_CHANNEL', 'env'];
        yield 'PW_CHANNEL in SERVER' => ['PW_CHANNEL', 'server'];
        yield 'PW_CHANNEL in process' => ['PW_CHANNEL', 'process'];
    }

    public function testPlaywrightChannelTakesPrecedenceOverLibraryChannel(): void
    {
        putenv('PLAYWRIGHT_CHANNEL=chrome');
        $_ENV['PW_CHANNEL'] = 'msedge';

        $browser = BrowserRegistry::fromEnvironment();

        $this->assertTrue($browser->equals(new BrowserRegistry(launchOptions: ['channel' => 'chrome'])));
    }

    public function testFromEnvironmentIgnoresEmptyChannel(): void
    {
        $_ENV['PLAYWRIGHT_CHANNEL'] = '';

        $browser = BrowserRegistry::fromEnvironment();

        $this->assertTrue($browser->equals(new BrowserRegistry()));
    }

    public function testChannelChangesMakeBrowserConfigurationsDifferent(): void
    {
        putenv('PW_CHANNEL=chrome');
        $first = BrowserRegistry::fromEnvironment();

        putenv('PW_CHANNEL=msedge');
        $second = BrowserRegistry::fromEnvironment();

        $this->assertFalse($first->equals($second));
    }

    public function testGetPageReturnsExistingPageWithoutStarting(): void
    {
        $page = new DummyPage();
        $context = new DummyBrowserContext($page);

        $browser = new BrowserRegistry('chromium', true);

        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');
        $refSession->setValue($browser, new BrowserSession($context));

        $result = $browser->getPage();

        $this->assertSame($page, $result);
    }

    public function testSetupRoutingRegistersRouteOnPage(): void
    {
        $page = new DummyPage();
        $context = new DummyBrowserContext($page);

        $browser = new BrowserRegistry('chromium', true);

        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');
        $refSession->setValue($browser, new BrowserSession($context));

        $handler = static function (): void {
        };

        $browser->setupRouting($handler);

        $this->assertNotEmpty($page->routes);
        $this->assertSame('**/*', $page->routes[0][0]);
        $this->assertSame($handler, $page->routes[0][1]);
    }

    public function testStopClosesContextAndClearsState(): void
    {
        $context = new DummyBrowserContext(new DummyPage());
        $driver = $this->createMock(BrowserInterface::class);
        $driver->expects($this->once())->method('newContext')->willReturn($context);
        $browser = new BrowserRegistry('chromium', true);
        $refBrowser = new \ReflectionProperty(BrowserRegistry::class, 'browser');
        $refBrowser->setValue($browser, $driver);
        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');

        $browser->start();

        $browser->stop();

        $this->assertTrue($context->closed);
        $this->assertNull($refSession->getValue($browser));
    }

    public function testStopClosesTheClientWhenTheContextCannotBeClosed(): void
    {
        $context = $this->createMock(BrowserContextInterface::class);
        $context->method('close')->willThrowException(new \RuntimeException('connection is broken'));

        $client = $this->createMock(PlaywrightClient::class);
        $client->expects($this->once())->method('close');
        $driver = $this->createMock(BrowserInterface::class);
        $driver->expects($this->once())->method('newContext')->willReturn($context);

        $browser = new BrowserRegistry('chromium', true);
        $refBrowser = new \ReflectionProperty(BrowserRegistry::class, 'browser');
        $refBrowser->setValue($browser, $driver);
        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');
        $refClient = new \ReflectionProperty(BrowserRegistry::class, 'client');
        $refClient->setValue($browser, $client);

        $browser->start();
        $browser->stop();

        $this->assertNull($refSession->getValue($browser));
        $this->assertNull($refClient->getValue($browser));
    }

    public function testCreateSessionReusesTheBrowser(): void
    {
        $firstPage = new DummyPage();
        $secondPage = new DummyPage();
        $firstContext = new DummyBrowserContext($firstPage);
        $secondContext = new DummyBrowserContext($secondPage);
        $driver = $this->createMock(BrowserInterface::class);
        $driver->expects($this->exactly(2))
            ->method('newContext')
            ->willReturnOnConsecutiveCalls(
                $firstContext,
                $secondContext,
            );

        $browser = new BrowserRegistry('chromium', true);
        $refBrowser = new \ReflectionProperty(BrowserRegistry::class, 'browser');
        $refBrowser->setValue($browser, $driver);

        $first = $browser->createSession();
        $second = $browser->createSession();

        $this->assertSame($firstPage, $first->getPage());
        $this->assertSame($secondPage, $second->getPage());

        $browser->stop();

        $this->assertTrue($firstContext->closed);
        $this->assertTrue($secondContext->closed);
    }

    public function testCloseSessionClosesAndUnregistersTheSession(): void
    {
        $context = $this->createMock(BrowserContextInterface::class);
        $context->expects($this->once())->method('close');
        $driver = $this->createMock(BrowserInterface::class);
        $driver->expects($this->once())->method('newContext')->willReturn($context);

        $browser = new BrowserRegistry();
        $refBrowser = new \ReflectionProperty(BrowserRegistry::class, 'browser');
        $refBrowser->setValue($browser, $driver);

        $session = $browser->createSession();
        $browser->closeSession($session);
        $browser->stop();
    }

    public function testCloseSessionClearsThePrimarySession(): void
    {
        $context = new DummyBrowserContext(new DummyPage());
        $driver = $this->createMock(BrowserInterface::class);
        $driver->expects($this->once())->method('newContext')->willReturn($context);

        $browser = new BrowserRegistry();
        $refBrowser = new \ReflectionProperty(BrowserRegistry::class, 'browser');
        $refBrowser->setValue($browser, $driver);
        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');

        $browser->start();
        $session = $refSession->getValue($browser);

        $this->assertInstanceOf(BrowserSession::class, $session);

        $browser->closeSession($session);

        $this->assertTrue($context->closed);
        $this->assertNull($refSession->getValue($browser));
    }

    public function testCloseSessionRejectsAnAlreadyClosedSession(): void
    {
        $context = new DummyBrowserContext(new DummyPage());
        $driver = $this->createMock(BrowserInterface::class);
        $driver->expects($this->once())->method('newContext')->willReturn($context);

        $browser = new BrowserRegistry();
        $refBrowser = new \ReflectionProperty(BrowserRegistry::class, 'browser');
        $refBrowser->setValue($browser, $driver);

        $session = $browser->createSession();
        $browser->closeSession($session);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The browser session is not managed by this registry.');

        $browser->closeSession($session);
    }

    public function testCloseSessionRejectsASessionFromAnotherRegistry(): void
    {
        $browser = new BrowserRegistry();
        $session = new BrowserSession(new DummyBrowserContext(new DummyPage()));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The browser session is not managed by this registry.');

        $browser->closeSession($session);
    }

    public function testCloseStopsTheRegistry(): void
    {
        $context = new DummyBrowserContext(new DummyPage());
        $driver = $this->createMock(BrowserInterface::class);
        $driver->expects($this->once())->method('newContext')->willReturn($context);
        $browser = new BrowserRegistry();
        $refBrowser = new \ReflectionProperty(BrowserRegistry::class, 'browser');
        $refBrowser->setValue($browser, $driver);
        $refSession = new \ReflectionProperty(BrowserRegistry::class, 'session');

        $browser->start();
        $browser->close();

        $this->assertTrue($context->closed);
        $this->assertNull($refSession->getValue($browser));
    }

    public function testResetSessionsKeepsTheBrowserReusable(): void
    {
        $firstContext = new DummyBrowserContext(new DummyPage());
        $secondContext = new DummyBrowserContext(new DummyPage());
        $driver = $this->createMock(BrowserInterface::class);
        $driver->expects($this->exactly(2))
            ->method('newContext')
            ->willReturnOnConsecutiveCalls($firstContext, $secondContext);
        $driver->expects($this->never())->method('close');

        $browser = new BrowserRegistry('chromium', true);
        $refBrowser = new \ReflectionProperty(BrowserRegistry::class, 'browser');
        $refBrowser->setValue($browser, $driver);

        $browser->start();
        $browser->resetSessions();
        $browser->start();

        $this->assertTrue($firstContext->closed);
        $this->assertFalse($secondContext->closed);
    }

    public function testEqualsReturnsTrueForSameBrowserConfiguration(): void
    {
        $browser1 = new BrowserRegistry('chromium', true, ['arg' => 'value']);
        $browser2 = new BrowserRegistry('chromium', true, ['arg' => 'value']);

        $this->assertTrue($browser1->equals($browser2));
    }

    public function testEqualsReturnsFalseForDifferentBrowserType(): void
    {
        $browser1 = new BrowserRegistry('chromium', true);
        $browser2 = new BrowserRegistry('firefox', true);

        $this->assertFalse($browser1->equals($browser2));
    }

    public function testEqualsReturnsFalseForDifferentHeadlessMode(): void
    {
        $browser1 = new BrowserRegistry('chromium', true);
        $browser2 = new BrowserRegistry('chromium', false);

        $this->assertFalse($browser1->equals($browser2));
    }

    public function testEqualsReturnsFalseForDifferentLaunchOptions(): void
    {
        $browser1 = new BrowserRegistry('chromium', true, ['option1' => 'value1']);
        $browser2 = new BrowserRegistry('chromium', true, ['option2' => 'value2']);

        $this->assertFalse($browser1->equals($browser2));
    }

    /**
     * @param array{string|false, mixed, mixed} $values
     */
    private function restoreEnvironmentVariable(string $name, array $values): void
    {
        [$process, $environment, $server] = $values;
        putenv(false === $process ? $name : $name.'='.$process);
        unset($_ENV[$name], $_SERVER[$name]);

        if (null !== $environment) {
            $_ENV[$name] = $environment;
        }

        if (null !== $server) {
            $_SERVER[$name] = $server;
        }
    }
}

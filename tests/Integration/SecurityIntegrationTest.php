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
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Asset\AssetMapperProxy;
use Playwright\Symfony\Asset\FilesystemProxy;
use Playwright\Symfony\Client\BrowserRegistry;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Client\PlaywrightKernelClient;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Client\Fixtures\FakeBrowserContext;
use Playwright\Symfony\Tests\Client\Fixtures\FakePage;
use Playwright\Symfony\Tests\Client\Fixtures\TestBrowserRegistry;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Playwright\Symfony\Util\CookieJarSync;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(PlaywrightKernelClient::class)]
#[CoversClass(PlaywrightTestCase::class)]
#[UsesClass(AssetMapperProxy::class)]
#[UsesClass(FilesystemProxy::class)]
#[UsesClass(BrowserRegistry::class)]
#[UsesClass(AssetServer::class)]
#[UsesClass(CookieJarSync::class)]
final class SecurityIntegrationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testLoginUserUsesSecurityBundleServicesFromTheTestContainer(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $testContainer = $container->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);

            $context = new FakeBrowserContext();
            $browser = new TestBrowserRegistry($context, new FakePage($context));
            $client = new PlaywrightKernelClient(
                $browser,
                $kernel,
                new RequestConverter(),
                new ResponseConverter(),
            );
            $user = new InMemoryUser('admin@example.test', null, ['ROLE_ADMIN']);

            self::assertSame($client, $client->loginUser($user));

            $tokenStorage = $testContainer->get('security.untracked_token_storage');
            self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
            self::assertSame($user, $tokenStorage->getToken()?->getUser());

            $session = $client->getSession();
            self::assertNotNull($session);
            self::assertNotNull($session->get('_security_main'));
            self::assertSame($session->getId(), $client->getCookie($session->getName()));
        } finally {
            $kernel->shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testPlaywrightTestCaseRegistersItsClientWithWebTestCase(): void
    {
        $_ENV['PLAYWRIGHT_E2E'] = '1';

        $context = new FakeBrowserContext();
        $browser = new TestBrowserRegistry($context, new FakePage($context));
        PlaywrightSetupTestCase::setSharedBrowser($browser);

        $testCase = new PlaywrightSetupTestCase('testPlaceholder');

        try {
            $testCase->runSetUp();

            self::assertSame($testCase->getPlaywrightClient(), $testCase->getRegisteredClient());
        } finally {
            $testCase->runTearDown();
            PlaywrightSetupTestCase::tearDownAfterClass();
            unset($_ENV['PLAYWRIGHT_E2E']);
        }
    }
}

final class PlaywrightSetupTestCase extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public static function setSharedBrowser(BrowserRegistry $browser): void
    {
        self::$sharedBrowser = $browser;
    }

    public function runSetUp(): void
    {
        $this->setUp();
    }

    public function runTearDown(): void
    {
        $this->tearDown();
    }

    public function getPlaywrightClient(): PlaywrightKernelClient
    {
        return $this->client;
    }

    public function getRegisteredClient(): ?AbstractBrowser
    {
        return self::getClient();
    }

    public function testPlaceholder(): void
    {
    }
}

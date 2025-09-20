<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Symfony\DependencyInjection\Configuration;
use Playwright\Symfony\DependencyInjection\PlaywrightExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(PlaywrightExtension::class)]
#[UsesClass(Configuration::class)]
class PlaywrightExtensionTest extends TestCase
{
    private PlaywrightExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new PlaywrightExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoad(): void
    {
        $config = [
            [
                'enabled' => true,
                'intercepted_hosts' => ['localhost', '127.0.0.1'],
                'debug' => true,
            ],
        ];

        $this->extension->load($config, $this->container);

        $this->assertTrue($this->container->hasParameter('playwright.intercepted_hosts'));
        $this->assertTrue($this->container->hasParameter('playwright.debug'));
        $this->assertEquals(['localhost', '127.0.0.1'], $this->container->getParameter('playwright.intercepted_hosts'));
        $this->assertTrue($this->container->getParameter('playwright.debug'));
    }

    public function testLoadWithDefaultConfig(): void
    {
        $this->extension->load([[]], $this->container);

        $this->assertTrue($this->container->hasParameter('playwright.intercepted_hosts'));
        $this->assertTrue($this->container->hasParameter('playwright.debug'));
        $this->assertEquals(['localhost', '127.0.0.1', 'testapp.local'], $this->container->getParameter('playwright.intercepted_hosts'));
    }

    public function testLoadDisabled(): void
    {
        $config = [['enabled' => false]];

        $this->extension->load($config, $this->container);

        $this->assertFalse($this->container->hasParameter('playwright.intercepted_hosts'));
        $this->assertFalse($this->container->hasParameter('playwright.debug'));
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('playwright', $this->extension->getAlias());
    }

    public function testRegistersDefaultBrowserAlias(): void
    {
        $container = new ContainerBuilder();
        $extension = new PlaywrightExtension();

        $config = [
            [
                'enabled' => true,
                'browsers' => [
                    'default' => [
                        'type' => 'chromium',
                        'headless' => true,
                    ],
                ],
                'default_browser' => 'default',
            ],
        ];

        $extension->load($config, $container);

        $this->assertTrue($container->hasDefinition('playwright.browser.default'));
        $this->assertTrue($container->hasAlias('playwright.browser'));
        $this->assertTrue($container->hasAlias(BrowserContextInterface::class));
        $this->assertSame('playwright.browser.default', (string) $container->getAlias('playwright.browser'));
    }

    public function testRegistersNamedBrowsersAndAutowiredAliases(): void
    {
        $container = new ContainerBuilder();
        $extension = new PlaywrightExtension();

        $config = [
            [
                'enabled' => true,
                'browsers' => [
                    'default' => [
                        'type' => 'chromium',
                    ],
                    'firefox_debug' => [
                        'type' => 'firefox',
                        'headless' => false,
                        'timeout_ms' => 10000,
                    ],
                ],
                'default_browser' => 'firefox_debug',
            ],
        ];

        $extension->load($config, $container);

        $this->assertTrue($container->hasDefinition('playwright.browser.default'));
        $this->assertTrue($container->hasDefinition('playwright.browser.firefox_debug'));

        // Default aliases point to firefox_debug
        $this->assertTrue($container->hasAlias('playwright.browser'));
        $this->assertTrue($container->hasAlias(BrowserContextInterface::class));
        $this->assertSame('playwright.browser.firefox_debug', (string) $container->getAlias('playwright.browser'));

        // Named autowiring alias for constructor arg $firefoxDebug
        $this->assertTrue($container->hasAlias(BrowserContextInterface::class.' $firefoxDebug'));
        $this->assertSame('playwright.browser.firefox_debug', (string) $container->getAlias(BrowserContextInterface::class.' $firefoxDebug'));
    }
}

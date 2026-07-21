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

namespace Playwright\Symfony\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Browser\BrowserType;
use Playwright\Configuration\PlaywrightConfig;
use Playwright\Symfony\DependencyInjection\Configuration;
use Playwright\Symfony\DependencyInjection\PlaywrightExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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

    public function testLoadDisabledRegistersNoService(): void
    {
        $this->extension->load([['enabled' => false]], $this->container);

        $this->assertSame([], array_diff($this->container->getServiceIds(), ['service_container']));
    }

    public function testContainerCompilesWhenDisabled(): void
    {
        $this->extension->load([['enabled' => false]], $this->container);

        $this->container->compile();

        $this->assertTrue($this->container->isCompiled());
    }

    public function testContainerCompilesWhenEnabled(): void
    {
        $this->container->setParameter('kernel.project_dir', __DIR__);
        $this->container->setParameter('kernel.debug', false);

        $this->extension->load([['enabled' => true]], $this->container);

        $this->container->compile();

        $this->assertTrue($this->container->isCompiled());
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

    public function testBrowserConfigDefinitionMapsWiredOptions(): void
    {
        $container = new ContainerBuilder();
        $extension = new PlaywrightExtension();

        $extension->load([
            [
                'enabled' => true,
                'node_path' => '/opt/node/bin/node',
                'browsers' => [
                    'default' => [
                        'type' => 'webkit',
                        'headless' => false,
                        'timeout_ms' => 12000,
                        'slowmo_ms' => 25,
                        'args' => ['--foo'],
                        'env' => ['DEBUG' => 'pw:*'],
                        'screenshot_dir' => '/tmp/shots',
                    ],
                ],
            ],
        ], $container);

        $definition = $container->getDefinition('playwright.browser.default.config');

        $this->assertSame(PlaywrightConfig::class, $definition->getClass());
        $this->assertSame('/opt/node/bin/node', $definition->getArgument('$nodePath'));
        $this->assertSame(BrowserType::WEBKIT, $definition->getArgument('$browser'));
        $this->assertFalse($definition->getArgument('$headless'));
        $this->assertSame(12000, $definition->getArgument('$timeoutMs'));
        $this->assertSame(25, $definition->getArgument('$slowMoMs'));
        $this->assertSame(['--foo'], $definition->getArgument('$args'));
        $this->assertSame(['DEBUG' => 'pw:*'], $definition->getArgument('$env'));
        $this->assertSame('/tmp/shots', $definition->getArgument('$screenshotDir'));
    }

    public function testBrowserContextDefinitionUsesConfigOnlyFactory(): void
    {
        $container = new ContainerBuilder();
        $extension = new PlaywrightExtension();

        $extension->load([['enabled' => true]], $container);

        $definition = $container->getDefinition('playwright.browser.default');

        $this->assertSame([PlaywrightExtension::class, 'createBrowserContext'], $definition->getFactory());
        $this->assertCount(1, $definition->getArguments());
        $this->assertEquals(new Reference('playwright.browser.default.config'), $definition->getArgument(0));
    }

    public function testBrowserNodePathFallsBackToGlobalNodePath(): void
    {
        $container = new ContainerBuilder();
        $extension = new PlaywrightExtension();

        $extension->load([
            [
                'enabled' => true,
                'node_path' => '/global/node',
                'browsers' => [
                    'default' => [],
                    'custom' => ['node_path' => '/custom/node'],
                ],
            ],
        ], $container);

        $this->assertSame('/global/node', $container->getDefinition('playwright.browser.default.config')->getArgument('$nodePath'));
        $this->assertSame('/custom/node', $container->getDefinition('playwright.browser.custom.config')->getArgument('$nodePath'));
    }
}

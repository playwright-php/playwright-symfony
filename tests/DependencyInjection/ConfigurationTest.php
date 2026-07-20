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
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertTrue($config['enabled']);
        $this->assertEquals(['localhost', '127.0.0.1', 'testapp.local'], $config['intercepted_hosts']);
        $this->assertEquals('%kernel.debug%', $config['debug']);
        $this->assertNull($config['node_path']);

        $this->assertArrayHasKey('browsers', $config);
        $this->assertArrayHasKey('default', $config['browsers']);

        $defaultBrowser = $config['browsers']['default'];
        $this->assertEquals('chromium', $defaultBrowser['type']);
        $this->assertTrue($defaultBrowser['headless']);
        $this->assertEquals(30000, $defaultBrowser['timeout_ms']);
        $this->assertEquals(0, $defaultBrowser['slowmo_ms']);
        $this->assertEquals([], $defaultBrowser['args']);
        $this->assertEquals([], $defaultBrowser['env']);
    }

    public function testCustomConfiguration(): void
    {
        $inputConfig = [
            'enabled' => false,
            'intercepted_hosts' => ['example.com', 'test.local'],
            'debug' => true,
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        $this->assertFalse($config['enabled']);
        $this->assertEquals(['example.com', 'test.local'], $config['intercepted_hosts']);
        $this->assertTrue($config['debug']);
    }

    public function testEmptyInterceptedHosts(): void
    {
        $inputConfig = [
            'intercepted_hosts' => [],
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        $this->assertEquals([], $config['intercepted_hosts']);
    }

    public function testBooleanValues(): void
    {
        $inputConfig = [
            'enabled' => false,
            'debug' => false,
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        $this->assertFalse($config['enabled']);
        $this->assertFalse($config['debug']);
    }

    public function testCustomBrowserConfiguration(): void
    {
        $inputConfig = [
            'browsers' => [
                'firefox_headless' => [
                    'type' => 'firefox',
                    'headless' => true,
                    'timeout_ms' => 60000,
                    'slowmo_ms' => 100,
                ],
                'webkit_visible' => [
                    'type' => 'webkit',
                    'headless' => false,
                ],
            ],
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        $this->assertArrayHasKey('firefox_headless', $config['browsers']);
        $this->assertArrayHasKey('webkit_visible', $config['browsers']);

        $firefoxBrowser = $config['browsers']['firefox_headless'];
        $this->assertEquals('firefox', $firefoxBrowser['type']);
        $this->assertTrue($firefoxBrowser['headless']);
        $this->assertEquals(60000, $firefoxBrowser['timeout_ms']);
        $this->assertEquals(100, $firefoxBrowser['slowmo_ms']);

        $webkitBrowser = $config['browsers']['webkit_visible'];
        $this->assertEquals('webkit', $webkitBrowser['type']);
        $this->assertFalse($webkitBrowser['headless']);
        $this->assertEquals(30000, $webkitBrowser['timeout_ms']); // default
    }

    public function testBrowserAcceptsEnvAndPathOptions(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            [
                'browsers' => [
                    'custom' => [
                        'type' => 'firefox',
                        'headless' => false,
                        'timeout_ms' => 15000,
                        'slowmo_ms' => 50,
                        'args' => ['--no-sandbox'],
                        'env' => ['DEBUG' => 'pw:*'],
                        'node_path' => '/usr/local/bin/node',
                        'screenshot_dir' => '/tmp/screenshots',
                    ],
                ],
            ],
        ]);

        $browser = $config['browsers']['custom'];
        $this->assertSame(['DEBUG' => 'pw:*'], $browser['env']);
        $this->assertSame('/usr/local/bin/node', $browser['node_path']);
        $this->assertSame('/tmp/screenshots', $browser['screenshot_dir']);
    }

    public function testBrowserAcceptsAllWiredOptions(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['browsers' => ['custom' => [
                'channel' => 'chrome-beta',
                'min_node_version' => '22.0.0',
                'downloads_dir' => '/tmp/dl',
                'videos_dir' => '/tmp/vid',
                'tracing' => ['enabled' => true, 'dir' => '/tmp/tr', 'screenshots' => true, 'snapshots' => true],
                'proxy' => ['server' => 'http://proxy.local:8080', 'username' => 'u', 'password' => 'p', 'bypass' => 'localhost'],
            ]]],
        ]);

        $browser = $config['browsers']['custom'];
        $this->assertSame('chrome-beta', $browser['channel']);
        $this->assertSame('22.0.0', $browser['min_node_version']);
        $this->assertSame('/tmp/dl', $browser['downloads_dir']);
        $this->assertSame('/tmp/vid', $browser['videos_dir']);
        $this->assertTrue($browser['tracing']['enabled']);
        $this->assertSame('/tmp/tr', $browser['tracing']['dir']);
        $this->assertSame('http://proxy.local:8080', $browser['proxy']['server']);
        $this->assertSame('u', $browser['proxy']['username']);
    }

    public function testProxyRequiresAServer(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [
            ['browsers' => ['default' => ['proxy' => ['username' => 'u']]]],
        ]);
    }

    public function testInvalidBrowserType(): void
    {
        $inputConfig = [
            'browsers' => [
                'invalid' => [
                    'type' => 'invalid_browser',
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [$inputConfig]);
    }

    public function testBaseUrlDefaultDoesNotRequireEnvVar(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        // The "default::" processor resolves to null when PLAYWRIGHT_BASE_URL is not
        // defined, instead of throwing EnvNotFoundException at runtime.
        $this->assertSame('%env(default::PLAYWRIGHT_BASE_URL)%', $config['base_url']);
    }

    public function testCustomBaseUrl(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['base_url' => 'http://testapp.local:8080'],
        ]);

        $this->assertSame('http://testapp.local:8080', $config['base_url']);
    }

    public function testCustomNodePath(): void
    {
        $inputConfig = [
            'node_path' => '/usr/local/bin/node',
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        $this->assertEquals('/usr/local/bin/node', $config['node_path']);
    }

    public function testTreeBuilder(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();

        $this->assertEquals('playwright', $treeBuilder->buildTree()->getName());
    }
}

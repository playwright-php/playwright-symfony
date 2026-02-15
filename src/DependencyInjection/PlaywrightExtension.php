<?php

declare(strict_types=1);

/*
 * This file is part of the community-maintained Playwright PHP project.
 * It is not affiliated with or endorsed by Microsoft.
 *
 * (c) 2025-Present - Playwright PHP <https://github.com/playwright-php>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Playwright\Symfony\DependencyInjection;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Browser\BrowserType;
use Playwright\Configuration\PlaywrightConfig;
use Playwright\Playwright;
use Playwright\Symfony\Asset\AssetMapperProxy;
use Playwright\Symfony\Asset\FilesystemProxy;
use Playwright\Symfony\BrowserKit\PlaywrightClient as BrowserKitClient;
use Playwright\Symfony\Client\Interception\AssetServer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class PlaywrightExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        if (!$config['enabled']) {
            return;
        }

        $container->setParameter('playwright.intercepted_hosts', $config['intercepted_hosts']);
        $container->setParameter('playwright.debug', $config['debug']);
        $container->setParameter('playwright.playwright_path', $config['playwright_path']);
        $container->setParameter('playwright.node_path', $config['node_path']);
        $container->setParameter('playwright.base_url', $config['base_url']);
        $container->setParameter('playwright.debug_logging', $config['debug_logging']);
        $assetConfig = $config['assets'] ?? [];
        $container->setParameter('playwright.asset_prefixes', $assetConfig['prefixes'] ?? ['/assets', '/build', '/_framework/ux']);
        $container->setParameter('playwright.asset_public_roots', $assetConfig['public_roots'] ?? ['%kernel.project_dir%/public']);
        $container->setParameter('playwright.asset_dev_no_cache', $assetConfig['disable_cache'] ?? true);

        // Register Playwright browsers (default + named)
        $this->registerBrowsers($container, $config['browsers'] ?? [], $config['default_browser'] ?? 'default');

        $container->register(BrowserKitClient::class, BrowserKitClient::class)
            ->setFactory([BrowserKitClient::class, 'fromContext'])
            ->setArguments([
                new Reference(BrowserContextInterface::class),
            ])
            ->setPublic(true);
    }

    public function getAlias(): string
    {
        return 'playwright';
    }

    /**
     * @param array<string, mixed> $browsersConfig
     */
    private function registerBrowsers(ContainerBuilder $container, array $browsersConfig, string $defaultBrowser): void
    {
        // Ensure a default browser exists even if no config provided
        if (!isset($browsersConfig[$defaultBrowser])) {
            $browsersConfig[$defaultBrowser] = [];
        }

        $browserServiceIds = [];

        foreach ($browsersConfig as $name => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }

            /** @var array<string, mixed> $browserConfig */
            $browserConfig = $cfg;
            $serviceId = sprintf('playwright.browser.%s', $name);

            // Build PlaywrightConfig definition from array config
            $configDef = new Definition(PlaywrightConfig::class);
            $configArgs = $this->mapBrowserConfigArgs($browserConfig);
            $configDef->setArguments($configArgs);

            $container->setDefinition($serviceId.'.config', $configDef);

            // Create BrowserContextInterface via factory - ready-to-use browser context
            $browserContextDef = new Definition(BrowserContextInterface::class);
            $browserContextDef->setFactory([self::class, 'createBrowserContext']);
            $browserType = $cfg['type'] ?? 'chromium';
            $browserContextDef->setArguments([
                new Reference($serviceId.'.config'),
                is_string($browserType) ? $browserType : 'chromium',
            ]);

            $container->setDefinition($serviceId, $browserContextDef);
            $browserServiceIds[$name] = $serviceId;

            // Autowire named arguments like BrowserContextInterface $firefoxDebug
            $this->registerAutowiredBrowserAlias($container, $name, $serviceId);
        }

        // Default aliases similar to http_client
        if (isset($browserServiceIds[$defaultBrowser])) {
            $container->setAlias('playwright.browser', $browserServiceIds[$defaultBrowser])->setPublic(false);
            $container->setAlias(BrowserContextInterface::class, $browserServiceIds[$defaultBrowser])->setPublic(false);
        }
    }

    /**
     * @param array<string, mixed> $cfg
     *
     * @return array<int, mixed>
     */
    private function mapBrowserConfigArgs(array $cfg): array
    {
        // PlaywrightConfig constructor signature:
        // (nodePath, minNodeVersion, browser, channel, headless, timeoutMs, slowMoMs, args, env,
        //  downloadsDir, videosDir, screenshotDir, tracingEnabled, traceDir, traceScreenshots, traceSnapshots, proxy, logger)

        $browserType = $cfg['type'] ?? 'chromium';
        $browserMap = [
            'chromium' => BrowserType::CHROMIUM,
            'firefox' => BrowserType::FIREFOX,
            'webkit' => BrowserType::WEBKIT,
        ];
        $browserTypeKey = is_string($browserType) ? $browserType : 'chromium';
        $browserEnum = $browserMap[$browserTypeKey] ?? BrowserType::CHROMIUM;

        $tracing = is_array($cfg['tracing'] ?? null) ? $cfg['tracing'] : [];
        $proxy = is_array($cfg['proxy'] ?? null) ? $cfg['proxy'] : null;

        $args = [
            $cfg['node_path'] ?? null,
            $cfg['min_node_version'] ?? '18.0.0',
            $browserEnum,
            $cfg['channel'] ?? null,
            $cfg['headless'] ?? true,
            $cfg['timeout_ms'] ?? 30000,
            $cfg['slowmo_ms'] ?? 0,
            $cfg['args'] ?? [],
            $cfg['env'] ?? [],
            $cfg['downloads_dir'] ?? null,
            $cfg['videos_dir'] ?? null,
            $cfg['screenshot_dir'] ?? null,
            $tracing['enabled'] ?? false,
            $tracing['dir'] ?? null,
            $tracing['screenshots'] ?? false,
            $tracing['snapshots'] ?? false,
            null !== $proxy ? array_filter([
                'server' => $proxy['server'] ?? null,
                'username' => $proxy['username'] ?? null,
                'password' => $proxy['password'] ?? null,
                'bypass' => $proxy['bypass'] ?? null,
            ], static fn ($v) => null !== $v && '' !== $v) : null,
            null, // logger is injected by factory argument, not here
        ];

        return $args;
    }

    private function registerAutowiredBrowserAlias(ContainerBuilder $container, string $browserName, string $serviceId): void
    {
        $parameterName = $this->convertNameToCamelCase($browserName);

        $container->registerAliasForArgument($serviceId, BrowserContextInterface::class, $parameterName);
    }

    public static function createBrowserContext(PlaywrightConfig $config, string $browserType): BrowserContextInterface
    {
        // Use the static Playwright API to create a ready-to-use browser context
        $launchOptions = [
            'headless' => $config->headless,
            'args' => $config->args,
            'slowMo' => $config->slowMoMs,
            'timeout' => $config->timeoutMs,
        ];

        // Remove null/empty values
        $launchOptions = array_filter($launchOptions, static fn (mixed $value): bool => [] !== $value);

        return match ($browserType) {
            'firefox' => Playwright::firefox($launchOptions),
            'webkit' => Playwright::webkit($launchOptions),
            default => Playwright::chromium($launchOptions),
        };
    }

    private function convertNameToCamelCase(string $name): string
    {
        return lcfirst(str_replace('_', '', ucwords($name, '_')));
    }
}

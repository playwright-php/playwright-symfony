<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\DependencyInjection;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Browser\BrowserType;
use Playwright\Configuration\PlaywrightConfig;
use Playwright\Playwright;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
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

        // Register Playwright browsers (default + named)
        $this->registerBrowsers($container, $config['browsers'] ?? [], $config['default_browser'] ?? 'default');
    }

    public function getAlias(): string
    {
        return 'playwright';
    }

    private function registerBrowsers(ContainerBuilder $container, array $browsersConfig, string $defaultBrowser): void
    {
        // Ensure a default browser exists even if no config provided
        if (!isset($browsersConfig[$defaultBrowser])) {
            $browsersConfig[$defaultBrowser] = [];
        }

        $browserServiceIds = [];

        foreach ($browsersConfig as $name => $cfg) {
            $serviceId = sprintf('playwright.browser.%s', $name);

            // Build PlaywrightConfig definition from array config
            $configDef = new Definition(PlaywrightConfig::class);
            $configArgs = $this->mapBrowserConfigArgs($cfg);
            $configDef->setArguments($configArgs);

            $container->setDefinition($serviceId.'.config', $configDef);

            // Create BrowserContextInterface via factory - ready-to-use browser context
            $browserContextDef = new Definition(BrowserContextInterface::class);
            $browserContextDef->setFactory([self::class, 'createBrowserContext']);
            $browserContextDef->setArguments([
                new Reference($serviceId.'.config'),
                $cfg['type'] ?? 'chromium', // Pass the browser type string for the factory
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
        $browserEnum = $browserMap[$browserType] ?? BrowserType::CHROMIUM;

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
            $cfg['tracing']['enabled'] ?? false,
            $cfg['tracing']['dir'] ?? null,
            $cfg['tracing']['screenshots'] ?? false,
            $cfg['tracing']['snapshots'] ?? false,
            isset($cfg['proxy']) ? array_filter([
                'server' => $cfg['proxy']['server'] ?? null,
                'username' => $cfg['proxy']['username'] ?? null,
                'password' => $cfg['proxy']['password'] ?? null,
                'bypass' => $cfg['proxy']['bypass'] ?? null,
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
        $launchOptions = array_filter($launchOptions, fn ($value) => null !== $value && [] !== $value);

        return match ($browserType) {
            'firefox' => Playwright::firefox($launchOptions),
            'webkit' => Playwright::safari($launchOptions),
            default => Playwright::chromium($launchOptions),
        };
    }

    private function convertNameToCamelCase(string $name): string
    {
        return lcfirst(str_replace('_', '', ucwords($name, '_')));
    }
}

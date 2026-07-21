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

namespace Playwright\Symfony\DependencyInjection;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Browser\BrowserType;
use Playwright\Configuration\PlaywrightConfig;
use Playwright\PlaywrightClient;
use Playwright\PlaywrightFactory;
use Playwright\Symfony\BrowserKit\PlaywrightClient as BrowserKitClient;
use Psr\Log\NullLogger;
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
    /** @var list<PlaywrightClient> */
    private static array $clients = [];
    private static bool $shutdownRegistered = false;

    /**
     * When "playwright.enabled" is false, no service and no parameter is registered:
     * services.php references playwright.* parameters that are only set below, so
     * loading it for a disabled bundle would break container compilation.
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (!$config['enabled']) {
            return;
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $container->setParameter('playwright.intercepted_hosts', $config['intercepted_hosts']); // @phpstan-ignore argument.type
        $container->setParameter('playwright.debug', $config['debug']); // @phpstan-ignore argument.type
        $container->setParameter('playwright.base_url', $config['base_url']); // @phpstan-ignore argument.type
        $container->setParameter('playwright.debug_logging', $config['debug_logging']); // @phpstan-ignore argument.type
        $assetConfig = $config['assets'] ?? [];
        \assert(is_array($assetConfig));
        $container->setParameter('playwright.asset_prefixes', $assetConfig['prefixes'] ?? ['/assets', '/build', '/_framework/ux']); // @phpstan-ignore argument.type
        $container->setParameter('playwright.asset_public_roots', $assetConfig['public_roots'] ?? ['%kernel.project_dir%/public']); // @phpstan-ignore argument.type
        $container->setParameter('playwright.asset_dev_no_cache', $assetConfig['disable_cache'] ?? true); // @phpstan-ignore argument.type

        $browsersConfig = $config['browsers'] ?? [];
        \assert(is_array($browsersConfig));
        /** @var array<string, mixed> $browsersConfig */
        $defaultBrowser = $config['default_browser'] ?? 'default';
        \assert(is_string($defaultBrowser));
        $globalNodePath = $config['node_path'] ?? null;
        \assert(null === $globalNodePath || is_string($globalNodePath));
        $this->registerBrowsers($container, $browsersConfig, $defaultBrowser, $globalNodePath);

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
    private function registerBrowsers(ContainerBuilder $container, array $browsersConfig, string $defaultBrowser, ?string $globalNodePath): void
    {
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

            $configDef = new Definition(PlaywrightConfig::class);
            $configArgs = $this->mapBrowserConfigArgs($browserConfig, $globalNodePath);
            $configDef->setArguments($configArgs);

            $container->setDefinition($serviceId.'.config', $configDef);

            $browserContextDef = new Definition(BrowserContextInterface::class);
            $browserContextDef->setFactory([self::class, 'createBrowserContext']);
            $browserContextDef->setArguments([
                new Reference($serviceId.'.config'),
            ]);

            $container->setDefinition($serviceId, $browserContextDef);
            $browserServiceIds[$name] = $serviceId;

            $this->registerAutowiredBrowserAlias($container, $name, $serviceId);
        }

        if (isset($browserServiceIds[$defaultBrowser])) {
            $container->setAlias('playwright.browser', $browserServiceIds[$defaultBrowser])->setPublic(false);
            $container->setAlias(BrowserContextInterface::class, $browserServiceIds[$defaultBrowser])->setPublic(false);
        }
    }

    /**
     * Maps the bundle browser options onto named PlaywrightConfig constructor
     * arguments. Only options actually consumed by the library are mapped; the
     * configuration tree rejects the others.
     *
     * @param array<string, mixed> $cfg
     *
     * @return array<string, mixed>
     */
    private function mapBrowserConfigArgs(array $cfg, ?string $globalNodePath): array
    {
        $browserType = $cfg['type'] ?? 'chromium';
        $browserMap = [
            'chromium' => BrowserType::CHROMIUM,
            'firefox' => BrowserType::FIREFOX,
            'webkit' => BrowserType::WEBKIT,
        ];
        $browserTypeKey = is_string($browserType) ? $browserType : 'chromium';
        $browserEnum = $browserMap[$browserTypeKey] ?? BrowserType::CHROMIUM;

        return [
            '$nodePath' => $cfg['node_path'] ?? $globalNodePath,
            '$browser' => $browserEnum,
            '$headless' => $cfg['headless'] ?? true,
            '$timeoutMs' => $cfg['timeout_ms'] ?? 30000,
            '$slowMoMs' => $cfg['slowmo_ms'] ?? 0,
            '$args' => $cfg['args'] ?? [],
            '$env' => $cfg['env'] ?? [],
            '$screenshotDir' => $cfg['screenshot_dir'] ?? null,
        ];
    }

    private function registerAutowiredBrowserAlias(ContainerBuilder $container, string $browserName, string $serviceId): void
    {
        $parameterName = $this->convertNameToCamelCase($browserName);

        $container->registerAliasForArgument($serviceId, BrowserContextInterface::class, $parameterName);
    }

    /**
     * DI factory for browser context services.
     *
     * The whole PlaywrightConfig flows into the library: node_path, env and
     * timeout_ms reach the Node.js server transport, headless, slowmo_ms and
     * args reach the browser launch, screenshot_dir is used at runtime.
     */
    public static function createBrowserContext(PlaywrightConfig $config): BrowserContextInterface
    {
        $client = PlaywrightFactory::create($config, $config->logger ?? new NullLogger());
        self::registerClientForShutdown($client);

        $builder = match ($config->browser) {
            BrowserType::FIREFOX => $client->firefox(),
            BrowserType::WEBKIT => $client->webkit(),
            default => $client->chromium(),
        };

        return $builder->launch()->context();
    }

    /**
     * Keeps clients referenced until PHP shutdown: a garbage-collected client
     * disconnects its transport, which the browser context still uses.
     */
    private static function registerClientForShutdown(PlaywrightClient $client): void
    {
        self::$clients[] = $client;

        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;

        register_shutdown_function(static function (): void {
            foreach (self::$clients as $client) {
                try {
                    $client->close();
                } catch (\Throwable) {
                }
            }
            self::$clients = [];
        });
    }

    private function convertNameToCamelCase(string $name): string
    {
        return lcfirst(str_replace('_', '', ucwords($name, '_')));
    }
}

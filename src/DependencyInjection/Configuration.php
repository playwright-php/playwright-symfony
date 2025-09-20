<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration for the PlaywrightSymfonyBundle.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('playwright');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable Playwright Symfony integration')
                ->end()
                ->arrayNode('intercepted_hosts')
                    ->info('List of hosts to intercept for in-process handling')
                    ->defaultValue(['localhost', '127.0.0.1', 'testapp.local'])
                    ->scalarPrototype()->end()
                ->end()
                ->booleanNode('debug')
                    ->defaultValue('%kernel.debug%')
                    ->info('Enable debug mode for Playwright integration')
                ->end()
                ->scalarNode('playwright_path')
                    ->defaultValue('npx playwright')
                    ->info('Path to Playwright binary (npx playwright, ./vendor/bin/playwright, etc.)')
                ->end()
                ->scalarNode('node_path')
                    ->defaultValue('node')
                    ->info('Path to Node.js executable')
                ->end()
                ->scalarNode('default_browser')
                    ->defaultValue('default')
                    ->info('Name of the default Playwright browser to autowire')
                ->end()
                ->arrayNode('browsers')
                    ->info('Named Playwright browsers with per-browser configuration')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('type')->values(['chromium', 'firefox', 'webkit'])->defaultValue('chromium')->info('Browser engine type')->end()
                            ->scalarNode('channel')->defaultNull()->end()
                            ->booleanNode('headless')->defaultTrue()->end()
                            ->integerNode('timeout_ms')->defaultValue(30000)->min(0)->end()
                            ->integerNode('slowmo_ms')->defaultValue(0)->min(0)->end()
                            ->arrayNode('args')->scalarPrototype()->end()->defaultValue([])->end()
                            ->arrayNode('env')->useAttributeAsKey('name')->scalarPrototype()->end()->defaultValue([])->end()
                            ->scalarNode('node_path')->defaultNull()->end()
                            ->scalarNode('min_node_version')->defaultValue('18.0.0')->end()
                            ->scalarNode('downloads_dir')->defaultNull()->end()
                            ->scalarNode('videos_dir')->defaultNull()->end()
                            ->scalarNode('screenshot_dir')->defaultNull()->end()
                            ->arrayNode('tracing')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('enabled')->defaultFalse()->end()
                                    ->scalarNode('dir')->defaultNull()->end()
                                    ->booleanNode('screenshots')->defaultFalse()->end()
                                    ->booleanNode('snapshots')->defaultFalse()->end()
                                ->end()
                            ->end()
                            ->arrayNode('proxy')
                                ->children()
                                    ->scalarNode('server')->end()
                                    ->scalarNode('username')->defaultNull()->end()
                                    ->scalarNode('password')->defaultNull()->end()
                                    ->scalarNode('bypass')->defaultNull()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'default' => [
                            'type' => 'chromium',
                            'headless' => true,
                            'timeout_ms' => 30000,
                            'slowmo_ms' => 0,
                            'args' => [],
                            'env' => [],
                        ],
                    ])
                ->end()

            ->end();

        return $treeBuilder;
    }
}

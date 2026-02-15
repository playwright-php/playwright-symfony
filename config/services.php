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

use Playwright\Symfony\Asset\AssetMapperProxy;
use Playwright\Symfony\Asset\FilesystemProxy;
use Playwright\Symfony\Client\Interception\AssetServer;
use Playwright\Symfony\Client\RequestConverter;
use Playwright\Symfony\Client\ResponseConverter;
use Playwright\Symfony\Command\DebugPlaywrightCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(RequestConverter::class);
    $services->set(ResponseConverter::class);

    $services->set(FilesystemProxy::class)
        ->arg('$publicRoots', param('playwright.asset_public_roots'));

    $services->set(AssetMapperProxy::class)
        ->arg('$assetMapper', service('asset_mapper')->nullOnInvalid());

    $services->set(AssetServer::class)
        ->public()  // Make public so tests can retrieve it
        ->args([
            [
                service(AssetMapperProxy::class),
                service(FilesystemProxy::class),
            ],
            param('playwright.asset_prefixes'),
            param('playwright.asset_dev_no_cache'),
        ]);

    $services->set(DebugPlaywrightCommand::class);
};

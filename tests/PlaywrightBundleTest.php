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

namespace Playwright\Symfony\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\DependencyInjection\PlaywrightExtension;
use Playwright\Symfony\PlaywrightSymfonyBundle;

#[CoversClass(PlaywrightSymfonyBundle::class)]
class PlaywrightBundleTest extends TestCase
{
    public function testGetContainerExtension(): void
    {
        $bundle = new PlaywrightSymfonyBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(PlaywrightExtension::class, $extension);
    }

    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new PlaywrightSymfonyBundle();

        $this->assertInstanceOf(PlaywrightSymfonyBundle::class, $bundle);
    }
}

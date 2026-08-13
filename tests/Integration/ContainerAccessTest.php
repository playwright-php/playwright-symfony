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

use PHPUnit\Framework\Attributes\CoversNothing;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversNothing]
final class ContainerAccessTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function testGetContainerReturnsTheContainer(): void
    {
        $container = static::getPlaywrightClient()->getContainer();

        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertTrue($container->has('kernel'));
    }

    public function testGetContainerPrefersTheTestContainer(): void
    {
        $container = static::getPlaywrightClient()->getContainer();
        $realContainer = self::$kernel?->getContainer();

        self::assertNotNull($realContainer);
        self::assertNotSame($realContainer, $container, 'expected the test container, which exposes private services');
        self::assertSame($realContainer->get('test.service_container'), $container);
    }
}

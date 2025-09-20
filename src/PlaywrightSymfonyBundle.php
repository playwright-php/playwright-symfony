<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony;

use Playwright\Symfony\DependencyInjection\PlaywrightExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class PlaywrightSymfonyBundle extends AbstractBundle
{
    protected string $extensionAlias = 'playwright';

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new PlaywrightExtension();
    }
}

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

namespace Playwright\Symfony\Tests\Fixtures\App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

#[AsEventListener]
class TerminateRecorder
{
    /**
     * @var string[]
     */
    private array $paths = [];

    public function __invoke(TerminateEvent $event): void
    {
        $this->paths[] = $event->getRequest()->getPathInfo();
    }

    /**
     * @return string[]
     */
    public function paths(): array
    {
        return $this->paths;
    }
}

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

namespace Playwright\Symfony\Client;

use Playwright\Browser\BrowserContextInterface;
use Playwright\Page\PageInterface;

/**
 * A browser context and its page, isolated from the browser process lifecycle.
 *
 * @internal
 */
final class BrowserSession
{
    private ?BrowserContextInterface $context;
    private ?PageInterface $page;

    public function __construct(BrowserContextInterface $context)
    {
        $this->context = $context;
        $this->page = $context->newPage();
    }

    public function getContext(): ?BrowserContextInterface
    {
        return $this->context;
    }

    public function getPage(): ?PageInterface
    {
        return $this->page;
    }

    public function setupRouting(callable $routeHandler): void
    {
        $this->page?->route('**/*', $routeHandler);
    }

    public function close(): void
    {
        $context = $this->context;

        $this->page = null;
        $this->context = null;

        $context?->close();
    }
}

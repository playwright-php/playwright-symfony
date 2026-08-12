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
use Playwright\Browser\BrowserInterface;
use Playwright\Page\PageInterface;
use Playwright\PlaywrightClient;
use Playwright\PlaywrightFactory;

/**
 * Registry for managing Playwright browser lifecycle, configuration, and shared instances.
 *
 * This class acts as a factory and lifecycle manager for Playwright browser contexts and pages.
 * It handles browser startup, shutdown, context recreation, and provides a centralized point
 * for browser configuration (browser type, headless mode, launch options).
 *
 * Primary role in architecture:
 * - Used by PlaywrightTestCase to share a single browser instance across multiple tests
 * - Provides browser context and page instances to PlaywrightKernelClient
 * - Manages browser lifecycle hooks (start, stop, restartContext)
 * - Configures request routing for the kernel-based interception flow
 *
 * Key responsibilities:
 * - Start/stop Playwright browsers (chromium, firefox, webkit)
 * - Create and manage browser contexts and pages
 * - Restart contexts between tests for isolation while reusing browser instance (performance)
 * - Set up routing callbacks for request interception via setupRouting()
 * - Read configuration from environment variables (PLAYWRIGHT_BROWSER, PLAYWRIGHT_HEADLESS)
 *
 * Usage:
 * - Typically instantiated via BrowserRegistry::fromEnvironment() in PlaywrightTestCase
 * - Browser instance is shared across tests in the same test class (static $sharedBrowser)
 * - Context is restarted between tests to ensure test isolation
 *
 * This is NOT a browser itself - it's a registry/manager that creates and holds browser instances.
 *
 * @internal Used by PlaywrightTestCase and PlaywrightKernelClient
 *
 * @author Simon André <smn.andre@gmail.com>
 */
class BrowserRegistry
{
    private ?PlaywrightClient $client = null;
    private ?BrowserInterface $browser = null;
    private ?BrowserContextInterface $context = null;
    private ?PageInterface $page = null;

    /**
     * @param array<string, mixed> $launchOptions
     */
    public function __construct(
        private readonly string $browserType = 'chromium',
        private readonly bool $headless = true,
        private readonly array $launchOptions = [],
    ) {
    }

    public function start(): void
    {
        if (null !== $this->context) {
            return;
        }

        $this->browser ??= $this->launchBrowser();
        $this->context = $this->browser->newContext($this->contextOptions());
        $this->page = $this->context->newPage();
    }

    public function equals(self $other): bool
    {
        return $this->browserType === $other->browserType
            && $this->headless === $other->headless
            && $this->launchOptions === $other->launchOptions;
    }

    public function stop(): void
    {
        // each close is best-effort: a broken connection - an exception thrown while handling a
        // routed request is re-raised by every later call - would otherwise abort before the
        // client is closed, leaving its Node process running for the rest of the PHP process
        $this->closeQuietly($this->context);

        // the browser and its Node process outlive the context, so they need closing too
        $this->closeQuietly($this->browser);
        $this->closeQuietly($this->client);

        $this->page = null;
        $this->context = null;
        $this->browser = null;
        $this->client = null;
    }

    public function restartContext(): void
    {
        if (null !== $this->page) {
            $this->page->close();
            $this->page = null;
        }
        if (null !== $this->context) {
            $this->context->close();
            $this->context = null;
        }
        $this->start();
    }

    public function getContext(): ?BrowserContextInterface
    {
        $this->ensureStarted();

        return $this->context;
    }

    public function getPage(): ?PageInterface
    {
        $this->ensureStarted();

        return $this->page;
    }

    public function setupRouting(callable $routeHandler): void
    {
        $this->ensureStarted();
        if (null !== $this->page) {
            $this->page->route('**/*', $routeHandler);
        }
    }

    public function isHeadless(): bool
    {
        return $this->headless;
    }

    public function getBrowserType(): string
    {
        return $this->browserType;
    }

    public static function fromEnvironment(): self
    {
        /** @var string|null $env */
        $env = $_ENV['PLAYWRIGHT_BROWSER'] ?? $_SERVER['PLAYWRIGHT_BROWSER'] ?? getenv('PLAYWRIGHT_BROWSER');
        $browserType = strtolower((string) $env);
        if (!in_array($browserType, ['chromium', 'firefox', 'webkit'], true)) {
            $browserType = 'chromium';
        }

        $headless = 'false' !== ($_ENV['PLAYWRIGHT_HEADLESS'] ?? $_SERVER['PLAYWRIGHT_HEADLESS'] ?? getenv('PLAYWRIGHT_HEADLESS'));

        return new self($browserType, $headless);
    }

    /**
     * Launched here rather than via the Playwright facade: the facade discards the browser handle
     * and only closes its client on shutdown, so every context needed a whole new browser.
     */
    private function launchBrowser(): BrowserInterface
    {
        $this->client = PlaywrightFactory::create();

        $builder = match ($this->browserType) {
            'firefox' => $this->client->firefox(),
            'webkit' => $this->client->webkit(),
            default => $this->client->chromium(),
        };

        $builder->withHeadless($this->headless);

        $slowMo = $this->launchOptions['slowMo'] ?? null;

        if (is_numeric($slowMo)) {
            $builder->withSlowMo((int) $slowMo);
        }

        $args = $this->launchOptions['args'] ?? null;

        if (is_array($args) && [] !== $args) {
            $builder->withArgs(array_values(array_filter($args, 'is_string')));
        }

        return $builder->launch();
    }

    /**
     * @return array<string, mixed>
     */
    private function contextOptions(): array
    {
        $options = $this->launchOptions['context'] ?? null;

        if (!is_array($options)) {
            return [];
        }

        $typed = [];

        foreach ($options as $key => $value) {
            if (is_string($key)) {
                $typed[$key] = $value;
            }
        }

        return $typed;
    }

    private function closeQuietly(BrowserContextInterface|BrowserInterface|PlaywrightClient|null $closable): void
    {
        try {
            $closable?->close();
        } catch (\Throwable) {
            // already unusable, nothing left to salvage
        }
    }

    private function ensureStarted(): void
    {
        if (null === $this->context) {
            $this->start();
        }
    }
}

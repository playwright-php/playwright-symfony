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
 * Manages a shared Playwright browser and the isolated sessions created from it.
 *
 * This class acts as a factory and lifecycle manager for Playwright browser contexts and pages.
 * It handles browser startup, shutdown, session cleanup, and provides a centralized point for
 * browser configuration (browser type, headless mode, launch options).
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
 * - Close contexts between tests for isolation while reusing the browser instance
 * - Set up routing callbacks for request interception via setupRouting()
 * - Read configuration from environment variables (PLAYWRIGHT_BROWSER, PLAYWRIGHT_HEADLESS)
 *
 * Usage:
 * - Typically instantiated via BrowserRegistry::fromEnvironment() in PlaywrightTestCase
 * - Browser instance is shared across tests in the same test class (static $sharedBrowser)
 * - Sessions are closed between tests to ensure test isolation
 *
 * This is NOT a browser itself - it's a registry/manager that creates and holds browser instances.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
class BrowserRegistry implements BrowserSessionInterface
{
    private ?PlaywrightClient $client = null;
    private ?BrowserInterface $browser = null;
    private ?BrowserSessionInterface $session = null;
    /** @var array<int, BrowserSessionInterface> */
    private array $sessions = [];

    /**
     * Configures the browser process shared by sessions created from this registry.
     *
     * @param array<string, mixed> $launchOptions
     */
    public function __construct(
        private readonly string $browserType = 'chromium',
        private readonly bool $headless = true,
        private readonly array $launchOptions = [],
    ) {
    }

    /**
     * Starts the registry's primary session if it has not been started yet.
     */
    public function start(): void
    {
        if (null !== $this->session) {
            return;
        }

        $this->session = $this->createSession();
    }

    /**
     * Whether this registry uses the same browser configuration as another registry.
     */
    public function equals(self $other): bool
    {
        return $this->browserType === $other->browserType
            && $this->headless === $other->headless
            && $this->launchOptions === $other->launchOptions;
    }

    /**
     * Closes every session, the shared browser, and its Playwright client process.
     */
    public function stop(): void
    {
        // each close is best-effort: a broken connection - an exception thrown while handling a
        // routed request is re-raised by every later call - would otherwise abort before the
        // client is closed, leaving its Node process running for the rest of the PHP process
        $this->resetSessions();

        // the browser and its Node process outlive the context, so they need closing too
        $this->closeQuietly($this->browser);
        $this->closeQuietly($this->client);

        $this->browser = null;
        $this->client = null;
    }

    /**
     * Closes every managed session while keeping the shared browser reusable.
     */
    public function resetSessions(): void
    {
        foreach ($this->sessions as $session) {
            $this->closeQuietly($session);
        }

        $this->session = null;
        $this->sessions = [];
    }

    /**
     * Closes this registry and all resources it manages.
     */
    public function close(): void
    {
        $this->stop();
    }

    /**
     * Replaces the primary session while keeping the shared browser running.
     */
    public function restartContext(): void
    {
        $session = $this->session;
        $this->session = null;
        if (null !== $session) {
            unset($this->sessions[spl_object_id($session)]);
            $session->close();
        }

        $this->start();
    }

    /**
     * Returns the primary session's browser context, starting it when needed.
     */
    public function getContext(): ?BrowserContextInterface
    {
        $this->ensureStarted();

        return $this->session?->getContext();
    }

    /**
     * Returns the primary session's page, starting it when needed.
     */
    public function getPage(): ?PageInterface
    {
        $this->ensureStarted();

        return $this->session?->getPage();
    }

    /**
     * Registers request routing on the primary session's page.
     */
    public function setupRouting(callable $routeHandler): void
    {
        $this->ensureStarted();
        $this->session?->setupRouting($routeHandler);
    }

    /**
     * Whether the browser is configured to run headless.
     */
    public function isHeadless(): bool
    {
        return $this->headless;
    }

    /**
     * Returns the configured browser engine name.
     */
    public function getBrowserType(): string
    {
        return $this->browserType;
    }

    /**
     * Creates a registry from PLAYWRIGHT_BROWSER and PLAYWRIGHT_HEADLESS.
     */
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

    /**
     * Creates an isolated context and page while reusing the browser process.
     *
     * The returned session is managed by this registry and remains open until closeSession(),
     * resetSessions(), or stop() closes it.
     */
    public function createSession(): BrowserSessionInterface
    {
        $this->browser ??= $this->launchBrowser();
        $session = new BrowserSession($this->browser->newContext($this->contextOptions()));
        $this->sessions[spl_object_id($session)] = $session;

        return $session;
    }

    /**
     * Closes a session created by this registry without stopping the shared browser.
     *
     * @throws \InvalidArgumentException When the session is not managed by this registry
     */
    public function closeSession(BrowserSessionInterface $session): void
    {
        $id = spl_object_id($session);
        $managedSession = $this->sessions[$id] ?? null;

        if ($managedSession !== $session) {
            throw new \InvalidArgumentException('The browser session is not managed by this registry.');
        }

        unset($this->sessions[$id]);

        if ($this->session === $managedSession) {
            $this->session = null;
        }

        $managedSession->close();
    }

    private function closeQuietly(BrowserSessionInterface|BrowserInterface|PlaywrightClient|null $closable): void
    {
        try {
            $closable?->close();
        } catch (\Throwable) {
            // already unusable, nothing left to salvage
        }
    }

    private function ensureStarted(): void
    {
        if (null === $this->session) {
            $this->start();
        }
    }
}

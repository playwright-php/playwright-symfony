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

namespace Playwright\Symfony\Tests\Client\Fixtures;

use Playwright\API\APIRequestContextInterface;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Frame\FrameInterface;
use Playwright\Frame\FrameLocatorInterface;
use Playwright\Input\KeyboardInterface;
use Playwright\Input\MouseInterface;
use Playwright\Locator\LocatorInterface;
use Playwright\Network\RequestInterface;
use Playwright\Network\ResponseInterface;
use Playwright\Page\PageEventHandlerInterface;
use Playwright\Page\PageInterface;

class FakePage implements PageInterface
{
    public ?string $lastGoto = null;
    /** @var callable|null */
    private $routeHandler;

    public function __construct(private BrowserContextInterface $context)
    {
    }

    public function route(string $pattern, callable $handler): void
    {
        $this->routeHandler = $handler;
    }

    public function goto(string $url, \Playwright\Page\Options\GotoOptions|array $options = []): ?ResponseInterface
    {
        $this->lastGoto = $url;

        return null;
    }

    public function triggerRequest(RequestInterface $request): FakeRoute
    {
        $route = new FakeRoute($request);
        if ($this->routeHandler) {
            ($this->routeHandler)($route);
        }

        return $route;
    }

    public function locator(string $selector, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        return new MockLocator($this, $selector);
    }

    public function click(string $selector, \Playwright\Page\Options\ClickOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function altClick(string $selector, \Playwright\Page\Options\ClickOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function controlClick(string $selector, \Playwright\Page\Options\ClickOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function shiftClick(string $selector, \Playwright\Page\Options\ClickOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function type(string $selector, string $text, \Playwright\Page\Options\TypeOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function screenshot(?string $path = null, \Playwright\Page\Options\ScreenshotOptions|array $options = []): string
    {
        return '';
    }

    public function pdf(?string $path = null, \Playwright\Page\Options\PdfOptions|array $options = []): string
    {
        return '';
    }

    public function pdfContent(\Playwright\Page\Options\PdfOptions|array $options = []): string
    {
        return '';
    }

    public function content(): ?string
    {
        return '';
    }

    public function evaluate(string $expression, mixed $arg = null): mixed
    {
        return null;
    }

    public function evaluateHandle(string $expression, mixed $arg = null): mixed
    {
        return null;
    }

    public function waitForSelector(string $selector, \Playwright\Page\Options\WaitForSelectorOptions|array $options = []): ?LocatorInterface
    {
        return null;
    }

    public function close(): void
    {
    }

    public function bringToFront(): PageInterface
    {
        return $this;
    }

    public function context(): BrowserContextInterface
    {
        return $this->context;
    }

    public function cookies(?array $urls = null): array
    {
        return method_exists($this->context, 'cookies') ? $this->context->cookies($urls) : [];
    }

    public function goBack(\Playwright\Page\Options\NavigationHistoryOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function goForward(\Playwright\Page\Options\NavigationHistoryOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function reload(\Playwright\Page\Options\NavigationHistoryOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function setContent(string $html, \Playwright\Page\Options\SetContentOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function url(): string
    {
        return $this->lastGoto ?? 'http://localhost/';
    }

    public function title(): string
    {
        return 'fake';
    }

    public function viewportSize(): ?array
    {
        return ['width' => 800, 'height' => 600];
    }

    public function setViewportSize(int $width, int $height): PageInterface
    {
        return $this;
    }

    public function waitForLoadState(string $state = 'load', \Playwright\Page\Options\WaitForLoadStateOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function waitForURL($url, \Playwright\Page\Options\WaitForUrlOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function once(string $event, callable $handler): void
    {
    }

    public function addScriptTag(\Playwright\Page\Options\ScriptTagOptions|array $options): PageInterface
    {
        return $this;
    }

    public function addStyleTag(\Playwright\Page\Options\StyleTagOptions|array $options): PageInterface
    {
        return $this;
    }

    public function frameLocator(string $selector): FrameLocatorInterface
    {
        return new class implements FrameLocatorInterface {
            public function __call($n, $a)
            {
                return $this;
            }
        };
    }

    public function keyboard(): KeyboardInterface
    {
        return new class implements KeyboardInterface {
            public function __call($n, $a)
            {
                return null;
            }
        };
    }

    public function mouse(): MouseInterface
    {
        return new class implements MouseInterface {
            public function __call($n, $a)
            {
                return null;
            }
        };
    }

    public function events(): PageEventHandlerInterface
    {
        return new class implements PageEventHandlerInterface {
            public function __call($n, $a)
            {
                return $this;
            }
        };
    }

    public function unroute(string $url, ?callable $handler = null): void
    {
        $this->routeHandler = null;
    }

    public function handleDialog(string $dialogId, bool $accept, ?string $promptText = null): void
    {
    }

    public function getPageIdForTransport(): string
    {
        return 'fake-page-id';
    }

    public function waitForEvents(): void
    {
    }

    public function setInputFiles(string $selector, array $files, \Playwright\Page\Options\SetInputFilesOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function mainFrame(): FrameInterface
    {
        return new class implements FrameInterface {
            public function __call($n, $a)
            {
                return null;
            }
        };
    }

    public function frames(): array
    {
        return [];
    }

    public function frame(\Playwright\Page\Options\FrameQueryOptions|array $options): ?FrameInterface
    {
        return null;
    }

    public function getByAltText(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByLabel(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByPlaceholder(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByRole(string $role, \Playwright\Locator\Options\GetByRoleOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByTestId(string $testId, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByText(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByTitle(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function isClosed(): bool
    {
        // TODO: Implement isClosed() method.
    }

    public function setDefaultNavigationTimeout(int $timeout): PageInterface
    {
        // TODO: Implement setDefaultNavigationTimeout() method.
    }

    public function setDefaultTimeout(int $timeout): PageInterface
    {
        // TODO: Implement setDefaultTimeout() method.
    }

    public function waitForPopup(callable $action, \Playwright\Page\Options\WaitForPopupOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function waitForResponse($url, \Playwright\Page\Options\WaitForResponseOptions|array $options = []): ResponseInterface
    {
        return new class implements ResponseInterface {
            public function __call($name, $arguments)
            {
                return null;
            }
        };
    }

    public function request(): APIRequestContextInterface
    {
        return new class implements APIRequestContextInterface {
            public function __call($name, $arguments)
            {
                return null;
            }
        };
    }
}

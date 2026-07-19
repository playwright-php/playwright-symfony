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
use Playwright\Locator\Options\GetByRoleOptions;
use Playwright\Locator\Options\LocatorOptions;
use Playwright\Network\RequestInterface;
use Playwright\Network\ResponseInterface;
use Playwright\Page\Options\ClickOptions;
use Playwright\Page\Options\FrameQueryOptions;
use Playwright\Page\Options\GotoOptions;
use Playwright\Page\Options\NavigationHistoryOptions;
use Playwright\Page\Options\PdfOptions;
use Playwright\Page\Options\ScreenshotOptions;
use Playwright\Page\Options\ScriptTagOptions;
use Playwright\Page\Options\SetContentOptions;
use Playwright\Page\Options\SetInputFilesOptions;
use Playwright\Page\Options\StyleTagOptions;
use Playwright\Page\Options\TypeOptions;
use Playwright\Page\Options\WaitForFunctionOptions;
use Playwright\Page\Options\WaitForLoadStateOptions;
use Playwright\Page\Options\WaitForPopupOptions;
use Playwright\Page\Options\WaitForResponseOptions;
use Playwright\Page\Options\WaitForSelectorOptions;
use Playwright\Page\Options\WaitForUrlOptions;
use Playwright\Page\PageEventHandlerInterface;
use Playwright\Page\PageInterface;
use Playwright\Regex;

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

    public function goto(string $url, GotoOptions|array $options = []): ?ResponseInterface
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

    public function triggerRequestWithInvalidRoute(object $route): void
    {
        if ($this->routeHandler) {
            ($this->routeHandler)($route);
        }
    }

    public function locator(string $selector, LocatorOptions|array $options = []): LocatorInterface
    {
        return new MockLocator($this, $selector);
    }

    public function click(string $selector, ClickOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function altClick(string $selector, ClickOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function controlClick(string $selector, ClickOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function shiftClick(string $selector, ClickOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function type(string $selector, string $text, TypeOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function screenshot(?string $path = null, ScreenshotOptions|array $options = []): string
    {
        return '';
    }

    public function pdf(?string $path = null, PdfOptions|array $options = []): string
    {
        return '';
    }

    public function pdfContent(PdfOptions|array $options = []): string
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

    public function waitForSelector(string $selector, WaitForSelectorOptions|array $options = []): LocatorInterface
    {
        return new MockLocator($this, $selector);
    }

    public function waitForFunction(string $pageFunction, mixed $arg = null, WaitForFunctionOptions|array $options = []): PageInterface
    {
        return $this;
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

    public function goBack(NavigationHistoryOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function goForward(NavigationHistoryOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function reload(NavigationHistoryOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function setContent(string $html, SetContentOptions|array $options = []): PageInterface
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

    public function waitForLoadState(string $state = 'load', WaitForLoadStateOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function waitForURL($url, WaitForUrlOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function once(string $event, callable $handler): void
    {
    }

    public function addScriptTag(ScriptTagOptions|array $options): PageInterface
    {
        return $this;
    }

    public function addStyleTag(StyleTagOptions|array $options): PageInterface
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

    public function setInputFiles(string $selector, array $files, SetInputFilesOptions|array $options = []): PageInterface
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

    public function frame(FrameQueryOptions|array $options): ?FrameInterface
    {
        return null;
    }

    public function getByAltText(Regex|string $text, LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByLabel(Regex|string $text, LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByPlaceholder(Regex|string $text, LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByRole(string $role, GetByRoleOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByTestId(string $testId, LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByText(Regex|string $text, LocatorOptions|array $options = []): LocatorInterface
    {
        return $this->locator('fake-locator');
    }

    public function getByTitle(Regex|string $text, LocatorOptions|array $options = []): LocatorInterface
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

    public function waitForPopup(callable $action, WaitForPopupOptions|array $options = []): PageInterface
    {
        return $this;
    }

    public function waitForResponse($url, WaitForResponseOptions|array $options = []): ResponseInterface
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

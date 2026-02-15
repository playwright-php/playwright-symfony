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

namespace Playwright\Symfony\Tests\Fixtures\Browser;

use Playwright\API\APIRequestContextInterface;
use Playwright\API\APIResponseInterface;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Frame\FrameInterface;
use Playwright\Frame\FrameLocatorInterface;
use Playwright\Input\KeyboardInterface;
use Playwright\Input\MouseInterface;
use Playwright\Locator\LocatorInterface;
use Playwright\Network\ResponseInterface;
use Playwright\Page\PageEventHandlerInterface;
use Playwright\Page\PageInterface;

final class DummyPage implements PageInterface
{
    /** @var array<int, array{0: string, 1: callable}> */
    public array $routes = [];

    public function locator(string $selector, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function getByAltText(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function getByLabel(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function getByPlaceholder(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function getByRole(string $role, \Playwright\Locator\Options\GetByRoleOptions|array $options = []): LocatorInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function getByTestId(string $testId, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function getByText(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function getByTitle(string $text, \Playwright\Locator\Options\LocatorOptions|array $options = []): LocatorInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function goto(string $url, \Playwright\Page\Options\GotoOptions|array $options = []): ?ResponseInterface
    {
        return null;
    }

    public function click(string $selector, \Playwright\Page\Options\ClickOptions|array $options = []): self
    {
        return $this;
    }

    public function altClick(string $selector, \Playwright\Page\Options\ClickOptions|array $options = []): self
    {
        return $this;
    }

    public function controlClick(string $selector, \Playwright\Page\Options\ClickOptions|array $options = []): self
    {
        return $this;
    }

    public function shiftClick(string $selector, \Playwright\Page\Options\ClickOptions|array $options = []): self
    {
        return $this;
    }

    public function type(string $selector, string $text, \Playwright\Page\Options\TypeOptions|array $options = []): self
    {
        return $this;
    }

    public function screenshot(?string $path = null, \Playwright\Page\Options\ScreenshotOptions|array $options = []): string
    {
        return $path ?? '';
    }

    public function pdf(?string $path = null, \Playwright\Page\Options\PdfOptions|array $options = []): string
    {
        return $path ?? '';
    }

    public function pdfContent(\Playwright\Page\Options\PdfOptions|array $options = []): string
    {
        return '';
    }

    public function content(): ?string
    {
        return null;
    }

    public function evaluate(string $expression, mixed $arg = null): mixed
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

    public function isClosed(): bool
    {
        return false;
    }

    public function bringToFront(): self
    {
        return $this;
    }

    public function context(): BrowserContextInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function cookies(?array $urls = null): array
    {
        return [];
    }

    public function goBack(\Playwright\Page\Options\NavigationHistoryOptions|array $options = []): self
    {
        return $this;
    }

    public function goForward(\Playwright\Page\Options\NavigationHistoryOptions|array $options = []): self
    {
        return $this;
    }

    public function reload(\Playwright\Page\Options\NavigationHistoryOptions|array $options = []): self
    {
        return $this;
    }

    public function setContent(string $html, \Playwright\Page\Options\SetContentOptions|array $options = []): self
    {
        return $this;
    }

    public function url(): string
    {
        return '';
    }

    public function title(): string
    {
        return '';
    }

    public function viewportSize(): ?array
    {
        return null;
    }

    public function setViewportSize(int $width, int $height): self
    {
        return $this;
    }

    public function setDefaultNavigationTimeout(int $timeout): self
    {
        return $this;
    }

    public function setDefaultTimeout(int $timeout): self
    {
        return $this;
    }

    public function waitForLoadState(string $state = 'load', \Playwright\Page\Options\WaitForLoadStateOptions|array $options = []): self
    {
        return $this;
    }

    public function waitForURL($url, \Playwright\Page\Options\WaitForUrlOptions|array $options = []): self
    {
        return $this;
    }

    public function addScriptTag(\Playwright\Page\Options\ScriptTagOptions|array $options): self
    {
        return $this;
    }

    public function addStyleTag(\Playwright\Page\Options\StyleTagOptions|array $options): self
    {
        return $this;
    }

    public function frameLocator(string $selector): FrameLocatorInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function keyboard(): KeyboardInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function mouse(): MouseInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function events(): PageEventHandlerInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function route(string $url, callable $handler): void
    {
        $this->routes[] = [$url, $handler];
    }

    public function unroute(string $url, ?callable $handler = null): void
    {
    }

    public function handleDialog(string $dialogId, bool $accept, ?string $promptText = null): void
    {
    }

    public function getPageIdForTransport(): string
    {
        return 'dummy';
    }

    public function waitForEvents(): void
    {
    }

    public function waitForPopup(callable $action, \Playwright\Page\Options\WaitForPopupOptions|array $options = []): self
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

    public function setInputFiles(string $selector, array $files, \Playwright\Page\Options\SetInputFilesOptions|array $options = []): self
    {
        return $this;
    }

    public function mainFrame(): FrameInterface
    {
        throw new \BadMethodCallException('Not needed in DummyPage');
    }

    public function frames(): array
    {
        return [];
    }

    public function frame(\Playwright\Page\Options\FrameQueryOptions|array $options): ?FrameInterface
    {
        return null;
    }

    public function request(): APIRequestContextInterface
    {
        return new class implements APIRequestContextInterface {
            public function get(string $url, array $options = []): APIResponseInterface
            {
                return $this->fakeResponse();
            }

            public function post(string $url, array $options = []): APIResponseInterface
            {
                return $this->fakeResponse();
            }

            public function put(string $url, array $options = []): APIResponseInterface
            {
                return $this->fakeResponse();
            }

            public function patch(string $url, array $options = []): APIResponseInterface
            {
                return $this->fakeResponse();
            }

            public function delete(string $url, array $options = []): APIResponseInterface
            {
                return $this->fakeResponse();
            }

            public function head(string $url, array $options = []): APIResponseInterface
            {
                return $this->fakeResponse();
            }

            public function fetch(string $urlOrRequest, array $options = []): APIResponseInterface
            {
                return $this->fakeResponse();
            }

            public function storageState(?string $path = null): array
            {
                return [];
            }

            public function dispose(): void
            {
            }

            private function fakeResponse(): APIResponseInterface
            {
                return new class implements APIResponseInterface {
                };
            }
        };
    }
}

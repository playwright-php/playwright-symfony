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
use Playwright\Browser\StorageState;
use Playwright\Network\NetworkThrottling;
use Playwright\Page\PageInterface;

final class DummyBrowserContext implements BrowserContextInterface
{
    public bool $closed = false;

    public function __construct(
        private readonly PageInterface $page,
    ) {
    }

    public function addCookies(array $cookies): void
    {
    }

    public function addInitScript(string $script): void
    {
    }

    public function clearCookies(array $options = []): void
    {
    }

    public function deleteCookie(string $name): void
    {
    }

    public function clearPermissions(): void
    {
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function cookies(?array $urls = null): array
    {
        return [];
    }

    public function exposeBinding(string $name, callable $callback): void
    {
    }

    public function exposeFunction(string $name, callable $callback): void
    {
    }

    public function grantPermissions(array $permissions): void
    {
    }

    public function newPage(array $options = []): PageInterface
    {
        return $this->page;
    }

    public function pages(): array
    {
        return [$this->page];
    }

    public function storageState(?string $path = null): array
    {
        return [];
    }

    public function getStorageState(): StorageState
    {
        return new StorageState([]);
    }

    public function setStorageState(StorageState $storageState): void
    {
    }

    public function saveStorageState(string $filePath): void
    {
    }

    public function loadStorageState(string $filePath): void
    {
    }

    public function route(string $url, callable $handler): void
    {
    }

    public function unroute(string $url, ?callable $handler = null): void
    {
    }

    public function getEnv(string $name): ?string
    {
        return null;
    }

    public function startTracing(PageInterface $page, array $options = []): void
    {
    }

    public function stopTracing(PageInterface $page, string $path): void
    {
    }

    public function setNetworkThrottling(NetworkThrottling $throttling): void
    {
    }

    public function disableNetworkThrottling(): void
    {
    }

    public function waitForEvent(string $event, ?callable $predicate = null, ?int $timeout = null): array
    {
        return [];
    }

    public function waitForPopup(callable $action, array $options = []): PageInterface
    {
        return $this->page;
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

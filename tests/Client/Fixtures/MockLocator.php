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

use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;

class MockLocator implements LocatorInterface
{
    public function __construct(private FakePage $page, private string $selector)
    {
    }

    /**
     * @param array<mixed> $arguments
     */
    private function record(string $method, array $arguments): void
    {
        $this->page->locatorCalls[] = [
            'selector' => $this->selector,
            'method' => $method,
            'arguments' => $arguments,
        ];
    }

    public function click(mixed $options = []): void
    {
        $this->record('click', []);
    }

    public function fill(string $value, mixed $options = []): void
    {
        $this->record('fill', [$value]);
    }

    public function check(mixed $options = []): void
    {
        $this->record('check', []);
    }

    public function uncheck(mixed $options = []): void
    {
        $this->record('uncheck', []);
    }

    public function selectOption(mixed $values, mixed $options = []): array
    {
        $this->record('selectOption', [$values]);

        return [];
    }

    public function count(): int
    {
        return 1;
    }

    public function evaluate(string $expression, mixed $arg = null, mixed $options = []): mixed
    {
        return null;
    }

    public function __call($name, $arguments)
    {
        $this->record((string) $name, (array) $arguments);

        return null;
    }

    public function innerHTML(mixed $options = []): string
    {
        return '';
    }

    public function innerText(mixed $options = []): string
    {
        return '';
    }

    public function textContent(mixed $options = []): ?string
    {
        return '';
    }

    public function isVisible(mixed $options = []): bool
    {
        return true;
    }

    public function isHidden(mixed $options = []): bool
    {
        return false;
    }

    public function isEnabled(mixed $options = []): bool
    {
        return true;
    }

    public function isDisabled(mixed $options = []): bool
    {
        return false;
    }

    public function isEditable(mixed $options = []): bool
    {
        return true;
    }

    public function isChecked(mixed $options = []): bool
    {
        return false;
    }

    public function getAttribute(string $name, mixed $options = []): ?string
    {
        return null;
    }

    public function inputValue(mixed $options = []): string
    {
        return '';
    }

    public function press(string $key, mixed $options = []): void
    {
    }

    public function hover(mixed $options = []): void
    {
    }

    public function dblclick(mixed $options = []): void
    {
    }

    public function focus(mixed $options = []): void
    {
    }

    public function dispatchEvent(string $type, mixed $eventInit = null, mixed $options = []): void
    {
    }

    public function scrollIntoViewIfNeeded(mixed $options = []): void
    {
    }

    public function selectText(mixed $options = []): void
    {
    }

    public function setInputFiles(mixed $files, mixed $options = []): void
    {
    }

    public function tap(mixed $options = []): void
    {
    }

    public function waitFor(mixed $options = []): void
    {
    }

    public function allTextContents(): array
    {
        return [];
    }

    public function allInnerTexts(): array
    {
        return [];
    }

    public function boundingBox(mixed $options = []): ?array
    {
        return null;
    }

    public function clear(mixed $options = []): void
    {
    }

    public function dragTo(LocatorInterface $target, mixed $options = []): void
    {
    }

    public function frameLocator(string $selector): \Playwright\Frame\FrameLocatorInterface
    {
        return new class implements \Playwright\Frame\FrameLocatorInterface {
            public function __call($n, $a)
            {
                return $this;
            }
        };
    }

    public function getByAltText(mixed $text, mixed $options = []): LocatorInterface
    {
        return $this;
    }

    public function getByLabel(mixed $text, mixed $options = []): LocatorInterface
    {
        return $this;
    }

    public function getByPlaceholder(mixed $text, mixed $options = []): LocatorInterface
    {
        return $this;
    }

    public function getByRole(mixed $role, mixed $options = []): LocatorInterface
    {
        return $this;
    }

    public function getByTestId(mixed $testId, mixed $options = []): LocatorInterface
    {
        return $this;
    }

    public function getByText(mixed $text, mixed $options = []): LocatorInterface
    {
        return $this;
    }

    public function getByTitle(mixed $text, mixed $options = []): LocatorInterface
    {
        return $this;
    }

    public function highlight(): void
    {
    }

    public function locator(string $selector, mixed $options = []): LocatorInterface
    {
        return $this;
    }

    public function page(): PageInterface
    {
        return $this->page;
    }

    public function screenshot(?string $path = null, mixed $options = []): ?string
    {
        return '';
    }

    public function setChecked(bool $checked, mixed $options = []): void
    {
    }

    public function all(): array
    {
        return [$this];
    }

    public function and(LocatorInterface $other): LocatorInterface
    {
        return $this;
    }

    public function blur(mixed $options = []): void
    {
    }

    public function first(): LocatorInterface
    {
        return $this;
    }

    public function last(): LocatorInterface
    {
        return $this;
    }

    public function nth(int $index): LocatorInterface
    {
        return $this;
    }

    public function or(LocatorInterface $other): LocatorInterface
    {
        return $this;
    }

    public function type(string $text, mixed $options = []): void
    {
    }

    public function isAttached(mixed $options = []): bool
    {
        return true;
    }

    public function isEmpty(mixed $options = []): bool
    {
        return false;
    }

    public function isVisibleAsync(mixed $options = []): bool
    {
        return true;
    }

    public function pressSequentially(string $text, mixed $options = []): void
    {
    }

    public function ariaSnapshot(mixed $options = []): string
    {
        return '';
    }

    public function contentFrame(): \Playwright\Frame\FrameLocatorInterface
    {
        throw new \Exception('Not implemented');
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function filter(mixed $options = []): LocatorInterface
    {
        return $this;
    }

    public function describe(string $description): LocatorInterface
    {
        return $this;
    }
}

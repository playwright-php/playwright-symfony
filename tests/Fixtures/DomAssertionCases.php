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

namespace Playwright\Symfony\Tests\Fixtures;

final class DomAssertionCases
{
    /** @return iterable<string, array{string, list<int|string>, string, string, int}> */
    public static function changingDocuments(): iterable
    {
        yield 'insertion' => ['assertSelectorExists', ['p'], '', '<p>Ready</p>', 1];
        yield 'removal' => ['assertSelectorNotExists', ['p'], '<p>Loading</p>', '', 1];
        yield 'count' => ['assertSelectorCount', [2, 'p'], '<p>One</p>', '<p>One</p><p>Two</p>', 1];
        yield 'text contains' => ['assertSelectorTextContains', ['p', 'Ready'], '<p>Loading</p>', '<p>Ready now</p>', 2];
        yield 'text exact and normalized' => ['assertSelectorTextSame', ['p', 'Ready now'], '<p>Ready later</p>', '<p> Ready   now </p>', 2];
        yield 'negative text requires a node' => ['assertSelectorTextNotContains', ['p', 'Loading'], '', '<p>Ready</p>', 2];
        yield 'any contains' => ['assertAnySelectorTextContains', ['p', 'Ready'], '<p>First</p><p>Loading</p>', '<p>First</p><p>Ready now</p>', 2];
        yield 'any exact' => ['assertAnySelectorTextSame', ['p', 'Ready'], '<p>First</p><p>Loading</p>', '<p>First</p><p>Ready</p>', 2];
        yield 'none contains' => ['assertAnySelectorTextNotContains', ['p', 'Loading'], '<p>First</p><p>Loading</p>', '<p>First</p><p>Ready</p>', 2];
        yield 'title exact' => ['assertPageTitleSame', ['Ready'], '<title>Loading</title>', '<title>Ready</title>', 2];
        yield 'title contains' => ['assertPageTitleContains', ['Ready'], '<title>Loading</title>', '<title>Ready now</title>', 2];
        yield 'input same' => ['assertInputValueSame', ['name', 'Simon'], '<input name="name" value="">', '<input name="name" value="Simon">', 2];
        yield 'input different requires a node' => ['assertInputValueNotSame', ['name', 'Loading'], '', '<input name="name" value="Ready">', 2];
        yield 'checked' => ['assertCheckboxChecked', ['terms'], '<input name="terms" type="checkbox">', '<input name="terms" type="checkbox" checked>', 1];
        yield 'unchecked' => ['assertCheckboxNotChecked', ['terms'], '<input name="terms" type="checkbox" checked>', '<input name="terms" type="checkbox">', 1];
        yield 'form value' => ['assertFormValue', ['form', 'name', 'Simon'], '<form><input name="name" value=""></form>', '<form><input name="name" value="Simon"></form>', 3];
        yield 'form value removed' => ['assertNoFormValue', ['form', 'name'], '<form><input name="name" value="Simon"></form>', '<form></form>', 2];
    }
}

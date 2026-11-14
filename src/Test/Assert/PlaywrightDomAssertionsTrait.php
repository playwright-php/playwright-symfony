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

namespace Playwright\Symfony\Test\Assert;

use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\LogicalAnd;
use PHPUnit\Framework\Constraint\LogicalNot;
use Playwright\Symfony\Client\PlaywrightKernelClient;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as DomCrawlerConstraint;
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorExists;

/** Symfony DOM assertions evaluated repeatedly against the current browser document. */
trait PlaywrightDomAssertionsTrait
{
    public static function assertSelectorExists(string $selector, string $message = ''): void
    {
        self::assertDomConstraint(new CrawlerSelectorExists($selector), $message);
    }

    public static function assertSelectorNotExists(string $selector, string $message = ''): void
    {
        self::assertDomConstraint(new LogicalNot(new CrawlerSelectorExists($selector)), $message);
    }

    public static function assertSelectorCount(int $expectedCount, string $selector, string $message = ''): void
    {
        self::assertDomConstraint(new DomCrawlerConstraint\CrawlerSelectorCount($expectedCount, $selector), $message);
    }

    public static function assertSelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        self::assertDomConstraint(LogicalAnd::fromConstraints(new CrawlerSelectorExists($selector), new DomCrawlerConstraint\CrawlerSelectorTextContains($selector, $text)), $message);
    }

    public static function assertAnySelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        self::assertDomConstraint(LogicalAnd::fromConstraints(new CrawlerSelectorExists($selector), new DomCrawlerConstraint\CrawlerAnySelectorTextContains($selector, $text)), $message);
    }

    public static function assertSelectorTextSame(string $selector, string $text, string $message = ''): void
    {
        self::assertDomConstraint(LogicalAnd::fromConstraints(new CrawlerSelectorExists($selector), new DomCrawlerConstraint\CrawlerSelectorTextSame($selector, $text)), $message);
    }

    public static function assertAnySelectorTextSame(string $selector, string $text, string $message = ''): void
    {
        self::assertDomConstraint(LogicalAnd::fromConstraints(new CrawlerSelectorExists($selector), new DomCrawlerConstraint\CrawlerAnySelectorTextSame($selector, $text)), $message);
    }

    public static function assertSelectorTextNotContains(string $selector, string $text, string $message = ''): void
    {
        self::assertDomConstraint(LogicalAnd::fromConstraints(new CrawlerSelectorExists($selector), new LogicalNot(new DomCrawlerConstraint\CrawlerSelectorTextContains($selector, $text))), $message);
    }

    public static function assertAnySelectorTextNotContains(string $selector, string $text, string $message = ''): void
    {
        self::assertDomConstraint(LogicalAnd::fromConstraints(new CrawlerSelectorExists($selector), new LogicalNot(new DomCrawlerConstraint\CrawlerAnySelectorTextContains($selector, $text))), $message);
    }

    public static function assertPageTitleSame(string $expectedTitle, string $message = ''): void
    {
        static::assertSelectorTextSame('title', $expectedTitle, $message);
    }

    public static function assertPageTitleContains(string $expectedTitle, string $message = ''): void
    {
        static::assertSelectorTextContains('title', $expectedTitle, $message);
    }

    public static function assertInputValueSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        $selector = 'input[name="'.$fieldName.'"]';
        self::assertDomConstraint(LogicalAnd::fromConstraints(new CrawlerSelectorExists($selector), new DomCrawlerConstraint\CrawlerSelectorAttributeValueSame($selector, 'value', $expectedValue)), $message);
    }

    public static function assertInputValueNotSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        $selector = 'input[name="'.$fieldName.'"]';
        self::assertDomConstraint(LogicalAnd::fromConstraints(new CrawlerSelectorExists($selector), new LogicalNot(new DomCrawlerConstraint\CrawlerSelectorAttributeValueSame($selector, 'value', $expectedValue))), $message);
    }

    public static function assertCheckboxChecked(string $fieldName, string $message = ''): void
    {
        self::assertDomConstraint(new CrawlerSelectorExists('input[name="'.$fieldName.'"]:checked'), $message);
    }

    public static function assertCheckboxNotChecked(string $fieldName, string $message = ''): void
    {
        self::assertDomConstraint(new LogicalNot(new CrawlerSelectorExists('input[name="'.$fieldName.'"]:checked')), $message);
    }

    public static function assertFormValue(string $formSelector, string $fieldName, string $value, string $message = ''): void
    {
        $crawler = self::waitForDomConstraint(new Callback(static function (Crawler $crawler) use ($formSelector, $fieldName, $value): bool {
            $node = $crawler->filter($formSelector);
            if (0 === $node->count()) {
                return false;
            }
            $values = $node->form()->getValues();

            return array_key_exists($fieldName, $values) && $value === $values[$fieldName];
        }));
        $node = $crawler->filter($formSelector);
        self::assertNotEmpty($node, sprintf('Form "%s" not found.', $formSelector));
        $values = $node->form()->getValues();
        self::assertArrayHasKey($fieldName, $values, $message ?: sprintf('Field "%s" not found in form "%s".', $fieldName, $formSelector));
        self::assertSame($value, $values[$fieldName]);
    }

    public static function assertNoFormValue(string $formSelector, string $fieldName, string $message = ''): void
    {
        $crawler = self::waitForDomConstraint(new Callback(static function (Crawler $crawler) use ($formSelector, $fieldName): bool {
            $node = $crawler->filter($formSelector);

            return 0 !== $node->count() && !array_key_exists($fieldName, $node->form()->getValues());
        }));
        $node = $crawler->filter($formSelector);
        self::assertNotEmpty($node, sprintf('Form "%s" not found.', $formSelector));
        self::assertArrayNotHasKey($fieldName, $node->form()->getValues(), $message ?: sprintf('Field "%s" has a value in form "%s".', $fieldName, $formSelector));
    }

    protected static function getDomAssertionTimeout(): int
    {
        return 5000;
    }

    private static function assertDomConstraint(Constraint $constraint, string $message): void
    {
        self::assertThat(self::waitForDomConstraint($constraint), $constraint, $message);
    }

    private static function waitForDomConstraint(Constraint $constraint): Crawler
    {
        $deadline = hrtime(true) + max(0, static::getDomAssertionTimeout()) * 1_000_000;
        $lastCrawler = null;
        while (true) {
            $client = static::getClient();
            if (null === $client) {
                static::fail('A client must have a crawler to make assertions. Did you forget to make an HTTP request?');
            }
            // A navigation in progress counts as a failed attempt, so the deadline also bounds it.
            $crawler = $client instanceof PlaywrightKernelClient ? $client->readCurrentCrawler() : $client->getCrawler();
            if (null !== $crawler) {
                if ($constraint->evaluate($crawler, '', true)) {
                    return $crawler;
                }
                $lastCrawler = $crawler;
            }
            $remaining = $deadline - hrtime(true);
            if ($remaining <= 0) {
                return $lastCrawler ?? static::fail(sprintf('The page was still navigating when the DOM assertion timed out after %d ms.', static::getDomAssertionTimeout()));
            }
            usleep((int) min(50_000, ceil($remaining / 1000)));
        }
    }
}

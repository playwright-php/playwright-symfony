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

namespace Playwright\Symfony\Tests\Test;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Client\PlaywrightKernelClient;
use Playwright\Symfony\Test\Assert\PlaywrightDomAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\DomCrawlerAssertionsTrait;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DomCrawler\Crawler;

#[CoversTrait(PlaywrightDomAssertionsTrait::class)]
final class PlaywrightDomAssertionsTraitTest extends TestCase
{
    use PlaywrightDomAssertionsTrait;

    private static ?AbstractBrowser $client = null;
    private static int $timeout = 200;

    protected function setUp(): void
    {
        self::$timeout = 200;
    }

    protected function tearDown(): void
    {
        self::$client = null;
    }

    /** @return iterable<string, array{string, list<int|string>, string, string, int}> */
    public static function changingDocuments(): iterable
    {
        yield from \Playwright\Symfony\Tests\Fixtures\DomAssertionCases::changingDocuments();
    }

    /** @param list<int|string> $arguments */
    #[DataProvider('changingDocuments')]
    public function testItRetriesWithoutCountingIntermediateAttempts(string $method, array $arguments, string $beforeHtml, string $afterHtml, int $assertionCount): void
    {
        $client = $this->createMock(AbstractBrowser::class);
        $client->expects($this->exactly(2))->method('getCrawler')->willReturn(new Crawler($beforeHtml, 'http://localhost/'), new Crawler($afterHtml, 'http://localhost/'));
        self::$client = $client;
        $before = Assert::getCount();

        self::{$method}(...$arguments);

        self::assertSame($assertionCount, Assert::getCount() - $before);
    }

    public function testTimeoutKeepsTheCustomMessageAndTheFinalDomFailure(): void
    {
        self::$timeout = 0;
        $client = $this->createMock(AbstractBrowser::class);
        $client->expects($this->once())->method('getCrawler')->willReturn(new Crawler('<p>Loading</p>'));
        self::$client = $client;

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The async update never arrived');

        self::assertSelectorTextSame('p', 'Ready', 'The async update never arrived');
    }

    public function testFirstMatchTextDoesNotBecomeAnyMatchText(): void
    {
        self::$timeout = 0;
        $client = $this->createStub(AbstractBrowser::class);
        $client->method('getCrawler')->willReturn(new Crawler('<p>First</p><p>Ready</p>'));
        self::$client = $client;

        $this->expectException(ExpectationFailedException::class);

        self::assertSelectorTextSame('p', 'Ready');
    }

    public function testANavigationInProgressIsRetriedWithoutWaitingOutsideTheTimeout(): void
    {
        $client = $this->createMock(PlaywrightKernelClient::class);
        $client->expects($this->never())->method('getCrawler');
        $client->expects($this->exactly(2))->method('readCurrentCrawler')->willReturn(null, new Crawler('<p>Ready</p>'));
        self::$client = $client;

        self::assertSelectorExists('p');
    }

    public function testANavigationStillInProgressAtTheDeadlineFails(): void
    {
        self::$timeout = 20;
        $client = $this->createStub(PlaywrightKernelClient::class);
        $client->method('readCurrentCrawler')->willReturn(null);
        self::$client = $client;
        $start = hrtime(true);

        try {
            self::assertSelectorExists('p');
            self::fail('The DOM assertion passed.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('still navigating', $e->getMessage());
        }

        self::assertLessThan(200, (hrtime(true) - $start) / 1_000_000);
    }

    public function testInvalidSelectorsFailWithoutRetrying(): void
    {
        $client = $this->createMock(AbstractBrowser::class);
        $client->expects($this->once())->method('getCrawler')->willReturn(new Crawler('<p>Ready</p>'));
        self::$client = $client;

        $this->expectException(\Symfony\Component\CssSelector\Exception\SyntaxErrorException::class);

        self::assertSelectorExists('[');
    }

    public function testAllPublicSymfonyDomSignaturesArePreserved(): void
    {
        foreach ((new \ReflectionClass(DomCrawlerAssertionsTrait::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $native) {
            $override = new \ReflectionMethod(PlaywrightDomAssertionsTrait::class, $native->name);
            self::assertTrue($override->isStatic(), $native->name);
            self::assertSame((string) $native->getReturnType(), (string) $override->getReturnType(), $native->name);
            $signature = static fn (\ReflectionMethod $method): array => array_map(static fn (\ReflectionParameter $parameter): array => [
                $parameter->name,
                (string) $parameter->getType(),
                $parameter->isOptional() ? $parameter->getDefaultValue() : null,
            ], $method->getParameters());
            self::assertSame($signature($native), $signature($override), $native->name);
        }
    }

    protected static function getClient(?AbstractBrowser $newClient = null): ?AbstractBrowser
    {
        return self::$client;
    }

    protected static function getDomAssertionTimeout(): int
    {
        return self::$timeout;
    }
}

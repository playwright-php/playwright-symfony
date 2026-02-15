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

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class FakeLogger implements LoggerInterface
{
    /** @var array<int, array{level:string,message:string,context:array}> */
    public array $records = [];

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, (string) $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, (string) $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, (string) $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, (string) $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, (string) $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, (string) $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, (string) $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, (string) $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasRecord(string $level, ?callable $predicate = null): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] !== $level) {
                continue;
            }

            if (null === $predicate) {
                return true;
            }

            if ($predicate($record['context'], $record['message'])) {
                return true;
            }
        }

        return false;
    }
}

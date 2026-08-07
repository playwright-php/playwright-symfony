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

use Symfony\Component\Process\PhpSubprocess;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$projectDir = dirname(__DIR__);
$output = static function (string $type, string $buffer): void {
    fwrite(Process::ERR === $type ? STDERR : STDOUT, $buffer);
};

foreach ([
    ['cache:clear', '--no-warmup'],
    ['importmap:install'],
] as $command) {
    $process = new PhpSubprocess([
        $projectDir.'/tests/Fixtures/App/console',
        ...$command,
        '--no-interaction',
    ], $projectDir);
    $process->setTimeout(null);

    if (0 !== $exitCode = $process->run($output)) {
        exit($exitCode);
    }
}

$phpunit = new PhpSubprocess([
    $projectDir.'/vendor/bin/phpunit',
    '--testsuite=functional',
    ...array_slice($argv, 1),
], $projectDir);
$phpunit->setTimeout(null);

exit($phpunit->run($output));

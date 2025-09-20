<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
#[AsCommand(
    name: 'debug:playwright',
    description: 'Debug Playwright Symfony integration configuration'
)]
final class DebugPlaywrightCommand extends Command
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Playwright Symfony Configuration');

        $this->displayConfiguration($io);
        $this->displayEnvironment($io);
        $this->displayDependencies($io);

        $io->success('Playwright configuration displayed successfully');

        return Command::SUCCESS;
    }

    private function displayConfiguration(SymfonyStyle $io): void
    {
        $io->section('Bundle Configuration');

        $interceptedHosts = $this->parameterBag->get('playwright.intercepted_hosts');
        $debug = $this->parameterBag->get('playwright.debug');
        $playwrightPath = $this->parameterBag->get('playwright.playwright_path');
        $nodePath = $this->parameterBag->get('playwright.node_path');

        $io->table(
            ['Parameter', 'Value'],
            [
                ['Intercepted Hosts', implode(', ', $interceptedHosts)],
                ['Debug Mode', $debug ? 'Enabled' : 'Disabled'],
                ['Playwright Path', $playwrightPath],
                ['Node.js Path', $nodePath],
            ]
        );
    }

    private function displayEnvironment(SymfonyStyle $io): void
    {
        $io->section('Environment Information');

        $playwrightEnvVars = [
            'PLAYWRIGHT_HEADLESS',
            'PLAYWRIGHT_BROWSER',
            'PLAYWRIGHT_TIMEOUT',
        ];

        $envData = [];
        foreach ($playwrightEnvVars as $envVar) {
            $value = $_ENV[$envVar] ?? $_SERVER[$envVar] ?? 'Not set';
            $envData[] = [$envVar, $value];
        }

        $io->table(['Environment Variable', 'Value'], $envData);
    }

    private function displayDependencies(SymfonyStyle $io): void
    {
        $io->section('Dependencies Check');

        $dependencies = [
            'Playwright Binary' => $this->checkPlaywrightBinary(),
            'Node.js' => $this->checkNodeJs(),
            'Symfony Kernel' => class_exists('Symfony\Component\HttpKernel\Kernel'),
        ];

        $dependencyData = [];
        foreach ($dependencies as $name => $available) {
            $status = $available ? '<fg=green>✓ Available</>' : '<fg=red>✗ Missing</>';
            $dependencyData[] = [$name, $status];
        }

        $io->table(['Dependency', 'Status'], $dependencyData);
    }

    private function checkPlaywrightBinary(): bool
    {
        $playwrightPath = $this->parameterBag->get('playwright.playwright_path');
        $command = $playwrightPath.' --version';

        return $this->executeWithTimeout($command, 10);
    }

    private function checkNodeJs(): bool
    {
        $nodePath = $this->parameterBag->get('playwright.node_path');
        $command = $nodePath.' --version';

        return $this->executeWithTimeout($command, 10);
    }

    private function executeWithTimeout(string $command, int $timeoutSeconds = 10): bool
    {
        // Use timeout command on Unix systems, or timeout via proc_open for better control
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, use basic timeout handling
            $fullCommand = sprintf('timeout %d %s 2>nul', $timeoutSeconds, $command);
        } else {
            // On Unix systems (Linux, macOS), use timeout command
            $fullCommand = sprintf('timeout %d %s 2>/dev/null', $timeoutSeconds, $command);
        }

        exec($fullCommand, $output, $returnCode);

        // Return true if command succeeded (return code 0)
        // timeout command returns 124 on timeout, other codes for other failures
        return 0 === $returnCode;
    }
}

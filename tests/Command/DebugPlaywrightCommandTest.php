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

namespace Playwright\Symfony\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Playwright\Symfony\Command\DebugPlaywrightCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

#[CoversClass(DebugPlaywrightCommand::class)]
class DebugPlaywrightCommandTest extends TestCase
{
    private DebugPlaywrightCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $parameterBag = new ParameterBag([
            'playwright.intercepted_hosts' => ['localhost', '127.0.0.1', 'testapp.local'],
            'playwright.debug' => true,
            'playwright.playwright_path' => 'npx playwright',
            'playwright.node_path' => 'node',
        ]);

        $this->command = new DebugPlaywrightCommand($parameterBag);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecute(): void
    {
        $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Playwright Symfony Configuration', $output);
        $this->assertStringContainsString('Bundle Configuration', $output);
        $this->assertStringContainsString('Environment Information', $output);
        $this->assertStringContainsString('Dependencies Check', $output);
    }

    public function testDisplaysInterceptedHosts(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('localhost, 127.0.0.1, testapp.local', $output);
    }

    public function testDisplaysDebugMode(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Debug Mode', $output);
        $this->assertStringContainsString('Enabled', $output);
    }

    public function testDisplaysEnvironmentVariables(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('PLAYWRIGHT_HEADLESS', $output);
        $this->assertStringContainsString('PLAYWRIGHT_BROWSER', $output);
        $this->assertStringContainsString('PLAYWRIGHT_TIMEOUT', $output);
    }

    public function testDisplaysDependencies(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Playwright Binary', $output);
        $this->assertStringContainsString('Node.js', $output);
        $this->assertStringContainsString('Symfony Kernel', $output);
    }

    public function testWithDebugDisabled(): void
    {
        $parameterBag = new ParameterBag([
            'playwright.intercepted_hosts' => ['localhost'],
            'playwright.debug' => false,
            'playwright.playwright_path' => 'npx playwright',
            'playwright.node_path' => 'node',
        ]);

        $command = new DebugPlaywrightCommand($parameterBag);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Disabled', $output);
    }

    public function testCommandName(): void
    {
        $this->assertEquals('debug:playwright', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertEquals('Debug Playwright Symfony integration configuration', $this->command->getDescription());
    }

    public function testCommandShowsCustomBinaryPaths(): void
    {
        $parameterBag = new ParameterBag([
            'playwright.intercepted_hosts' => ['localhost', '127.0.0.1'],
            'playwright.debug' => true,
            'playwright.playwright_path' => '/custom/playwright',
            'playwright.node_path' => '/custom/node',
        ]);

        $command = new DebugPlaywrightCommand($parameterBag);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Check that custom binary paths are displayed
        $this->assertStringContainsString('Bundle Configuration', $output);
        $this->assertStringContainsString('Playwright Path', $output);
        $this->assertStringContainsString('/custom/playwright', $output);
        $this->assertStringContainsString('Node.js Path', $output);
        $this->assertStringContainsString('/custom/node', $output);
    }

    public function testCommandShowsDefaultBinaryPaths(): void
    {
        $parameterBag = new ParameterBag([
            'playwright.intercepted_hosts' => ['localhost'],
            'playwright.debug' => false,
            'playwright.playwright_path' => 'npx playwright', // default
            'playwright.node_path' => 'node', // default
        ]);

        $command = new DebugPlaywrightCommand($parameterBag);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('npx playwright', $output);
        $this->assertStringContainsString('node', $output);
        $this->assertStringContainsString('Debug Mode', $output);
        $this->assertStringContainsString('Disabled', $output);
    }
}

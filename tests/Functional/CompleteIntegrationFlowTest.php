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

namespace Playwright\Symfony\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\Service\UserRepository;

/**
 * Complete integration test demonstrating full-stack Playwright Symfony functionality.
 *
 * Tests key integration features:
 * - Authentication flow
 * - Twig template rendering with dynamic data
 * - Service container access
 * - Data persistence simulation
 * - Logout flow
 *
 * Note: Form submission with redirect skipped due to known redirect limitation in intercept mode
 */
#[Group('e2e')]
#[Group('integration')]
final class CompleteIntegrationFlowTest extends PlaywrightTestCase
{
    public function testCompleteUserProfileWorkflow(): void
    {
        // Phase 1: Authenticate user (sets AUTH cookie)
        $this->authenticate('testuser');

        // Phase 2: Navigate to profile page - accessible after authentication
        $this->visit('/profile');
        $this->assertPageContains('Welcome, testuser');

        // Phase 3: Verify Twig template rendered with service data
        $this->assertPageContains('Test User');
        $this->assertPageContains('test@example.com');

        // Phase 4: Verify data comes from service container
        $userRepo = static::getContainer()->get(UserRepository::class);
        $user = $userRepo->findByUsername('testuser');
        $this->assertNotNull($user);
        $this->assertSame('test@example.com', $user['email']);
        $this->assertPageContains($user['name']);

        // Phase 5: Directly update via service (simulating form submission)
        $userRepo->update($user['id'], ['email' => 'newemail@example.com']);

        // Phase 6: Reload page to see updated data
        $this->visit('/profile');
        $this->assertPageContains('newemail@example.com');

        // Phase 7: Verify data persisted
        $updatedUser = $userRepo->findByUsername('testuser');
        $this->assertSame('newemail@example.com', $updatedUser['email']);

        // Phase 8: Logout and verify authentication is required
        $this->logout();
        $this->visit('/login');
        $this->assertPageContains('Authentication Required');
    }
}

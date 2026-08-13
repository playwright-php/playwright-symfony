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

use Playwright\Symfony\Test\PlaywrightTestCase;
use Playwright\Symfony\Tests\Fixtures\App\Service\UserRepository;
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

class ServiceContainerTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    /**
     * Test accessing a custom service from the container.
     */
    public function testAccessCustomServiceFromContainer(): void
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);

        static::assertInstanceOf(UserRepository::class, $userRepository);
        static::assertGreaterThanOrEqual(3, $userRepository->count());
    }

    /**
     * Test that controller receives injected service and returns data.
     */
    public function testControllerUsesInjectedService(): void
    {
        $this->visit('/service/users');

        $content = $this->getPage()->content();
        static::assertStringContainsString('Alice', $content);
        static::assertStringContainsString('Bob', $content);
        static::assertStringContainsString('Charlie', $content);
        static::assertStringContainsString('"total":', $content);
    }

    /**
     * Test accessing service data via controller during request.
     */
    public function testServiceMethodCalledFromController(): void
    {
        $this->visit('/service/user?id=1');

        $content = $this->getPage()->content();
        static::assertStringContainsString('"name":"Alice"', $content);
        static::assertStringContainsString('"email":"alice@example.com"', $content);
    }

    /**
     * Test service returns 404 when entity not found.
     */
    public function testServiceReturns404ForNonExistentUser(): void
    {
        $this->visit('/service/user?id=999');

        $response = $this->getLastResponse();
        static::assertNotNull($response);
        static::assertSame(404, $response->getStatusCode());

        $content = $this->getPage()->content();
        static::assertStringContainsString('User not found', $content);
    }

    /**
     * Test modifying service state during test.
     */
    public function testModifyingServiceStateDuringTest(): void
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);

        $initialCount = $userRepository->count();

        $newUser = [
            'name' => 'David',
            'email' => 'david@example.com',
        ];
        $userRepository->save($newUser);

        static::assertSame($initialCount + 1, $userRepository->count());

        $this->visit('/service/users');
        $content = $this->getPage()->content();
        static::assertStringContainsString('David', $content);
    }

    /**
     * Test creating user via POST request.
     */
    public function testCreatingUserViaPostRequest(): void
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $initialCount = $userRepository->count();

        $userData = [
            'name' => 'Eve',
            'email' => 'eve@example.com',
        ];

        $this->visit('/hello');

        $result = $this->getPage()->evaluate('async (data) => {
            const response = await fetch("/service/user", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(data)
            });
            return {
                status: response.status,
                body: await response.text()
            };
        }', $userData);

        static::assertSame(201, $result['status']);
        static::assertStringContainsString('"success":true', $result['body']);
        static::assertStringContainsString('Eve', $result['body']);

        static::assertSame($initialCount + 1, $userRepository->count());
    }

    /**
     * Test validation error when creating user without required fields.
     */
    public function testValidationErrorWhenCreatingUserWithoutRequiredFields(): void
    {
        $this->visit('/hello');

        $result = $this->getPage()->evaluate('async (data) => {
            const response = await fetch("/service/user", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(data)
            });
            return {
                status: response.status,
                body: await response.text()
            };
        }', ['name' => 'Incomplete']);

        static::assertSame(400, $result['status']);
        static::assertStringContainsString('Name and email are required', $result['body']);
    }
}

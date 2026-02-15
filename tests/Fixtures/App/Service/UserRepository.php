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

namespace Playwright\Symfony\Tests\Fixtures\App\Service;

class UserRepository
{
    /**
     * @var array<int, array{id: int, name: string, email: string, username: string}>
     */
    private array $users = [];

    public function __construct()
    {
        $this->users = [
            1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'username' => 'alice'],
            2 => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'username' => 'bob'],
            3 => ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com', 'username' => 'charlie'],
            4 => ['id' => 4, 'name' => 'Test User', 'email' => 'test@example.com', 'username' => 'testuser'],
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, username: string}|null
     */
    public function findById(int $id): ?array
    {
        return $this->users[$id] ?? null;
    }

    /**
     * @return array{id: int, name: string, email: string, username: string}|null
     */
    public function findByUsername(string $username): ?array
    {
        foreach ($this->users as $user) {
            if ($user['username'] === $username) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{id: int, name: string, email: string, username: string}>
     */
    public function findAll(): array
    {
        return array_values($this->users);
    }

    /**
     * @param array{id?: int, name: string, email: string, username?: string} $user
     */
    public function save(array $user): void
    {
        $id = $user['id'] ?? (count($this->users) + 1);
        $user['id'] = $id;
        $this->users[$id] = $user;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        if (!isset($this->users[$id])) {
            return false;
        }
        $this->users[$id] = array_merge($this->users[$id], $data);

        return true;
    }

    public function count(): int
    {
        return count($this->users);
    }
}

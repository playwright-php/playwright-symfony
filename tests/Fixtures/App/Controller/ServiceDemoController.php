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

namespace Playwright\Symfony\Tests\Fixtures\App\Controller;

use Playwright\Symfony\Tests\Fixtures\App\Service\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceDemoController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function listUsers(): Response
    {
        $users = $this->userRepository->findAll();

        return new JsonResponse([
            'users' => $users,
            'total' => $this->userRepository->count(),
        ]);
    }

    public function getUser(Request $request): Response
    {
        $id = (int) $request->query->get('id', 0);
        $user = $this->userRepository->findById($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        return new JsonResponse(['user' => $user]);
    }

    public function createUser(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name']) || !isset($data['email'])) {
            return new JsonResponse(['error' => 'Name and email are required'], 400);
        }

        $user = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        $this->userRepository->save($user);

        return new JsonResponse(['success' => true, 'user' => $user], 201);
    }
}

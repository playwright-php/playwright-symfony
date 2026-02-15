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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class ProfileController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly Environment $twig,
    ) {
    }

    public function login(Request $request): Response
    {
        return new Response(
            $this->twig->render('profile/login.html.twig')
        );
    }

    public function profile(Request $request): Response
    {
        // Check session username OR AUTH cookie
        $username = $request->getSession()->get('username');
        if (!$username) {
            // Try AUTH cookie set by authenticate() helper
            $authCookie = $request->cookies->get('AUTH');
            if ($authCookie) {
                $authData = json_decode($authCookie, true);
                $username = $authData['id'] ?? null;
            }
        }

        if (!$username) {
            return new Response(
                $this->twig->render('profile/login.html.twig'),
                200  // Return 200 to avoid redirect
            );
        }

        $user = $this->userRepository->findByUsername($username);

        if (!$user) {
            return new Response(
                $this->twig->render('profile/login.html.twig'),
                200
            );
        }

        return new Response(
            $this->twig->render('profile/profile.html.twig', [
                'user' => $user,
            ])
        );
    }

    public function update(Request $request): Response
    {
        // Check session username OR AUTH cookie
        $username = $request->getSession()->get('username');
        if (!$username) {
            $authCookie = $request->cookies->get('AUTH');
            if ($authCookie) {
                $authData = json_decode($authCookie, true);
                $username = $authData['id'] ?? null;
            }
        }

        if (!$username) {
            return new Response(
                $this->twig->render('profile/login.html.twig'),
                200
            );
        }

        $user = $this->userRepository->findByUsername($username);

        if (!$user) {
            return new Response(
                $this->twig->render('profile/login.html.twig'),
                200
            );
        }

        $email = $request->request->get('email');

        if ($email) {
            $this->userRepository->update($user['id'], ['email' => $email]);
        }

        return new RedirectResponse('/profile?success=1');
    }
}

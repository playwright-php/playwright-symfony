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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Twig\Environment;

#[AsController]
final readonly class UxController
{
    public function __construct(private Environment $twig)
    {
    }

    public function turbo(): Response
    {
        return new Response($this->twig->render('ux/turbo.html.twig'));
    }

    public function turboRedirect(): RedirectResponse
    {
        return new RedirectResponse('/ux/turbo/final');
    }

    public function turboFinal(): Response
    {
        return new Response($this->twig->render('ux/turbo_final.html.twig'));
    }

    public function turboFrameRedirect(): RedirectResponse
    {
        return new RedirectResponse('/ux/turbo/frame/final');
    }

    public function turboFrameFinal(): Response
    {
        return new Response($this->twig->render('ux/turbo_frame_final.html.twig'));
    }

    public function live(): Response
    {
        return new Response($this->twig->render('ux/live.html.twig'));
    }

    public function liveFinal(): Response
    {
        return new Response($this->twig->render('ux/live_final.html.twig'));
    }
}

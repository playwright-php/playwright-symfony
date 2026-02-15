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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class TwigDemoController extends AbstractController
{
    public function demo(): Response
    {
        return $this->render('assetmapper/demo.html.twig', [
            'title' => 'Twig Template Demo',
            'message' => 'This page is rendered using Twig templates!',
        ]);
    }
}

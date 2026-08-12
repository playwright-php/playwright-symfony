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
use Playwright\Symfony\Tests\Fixtures\App\TestKernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class CookieAndAuthTest extends PlaywrightTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testCookieHelpersAndAuthenticationLifecycle(): void
    {
        $this->setCookie('notice', '1');
        $this->visit('/cookie');
        $this->assertPageContains('"notice":"1"');
        self::assertSame('1', $this->getCookie('notice'));

        $this->clearCookie('notice');
        self::assertNull($this->getCookie('notice'));

        $this->authenticate('admin@example.test', ['roles' => ['ROLE_ADMIN']]);
        $this->visit('/protected');
        $this->assertPageContains('You are in');
        self::assertNotNull($this->getCookie('AUTH'));

        $this->logout();
        $this->visit('/protected');
        $this->assertPageContains('Access Denied');
        self::assertNull($this->getCookie('AUTH'));

        $this->clearCookies();
        self::assertNull($this->getCookie('notice'));
    }

    public function testLoginUserAuthenticatesTheRealSymfonySecurityFirewall(): void
    {
        $provider = static::getContainer()->get('security.user.provider.concrete.test_users');
        self::assertInstanceOf(UserProviderInterface::class, $provider);
        $user = $provider->loadUserByIdentifier('admin@example.test');

        $this->loginUser($user);
        $session = $this->client->getSession();
        self::assertNotNull($session);
        self::assertSame($session->getId(), $this->getCookie($session->getName()));
        self::assertNotNull($session->get('_security_main'));
        self::assertBrowserHasCookie($session->getName());

        $this->visit('/cookie');
        $this->assertPageContains('"'.$session->getName().'":"'.$session->getId().'"');

        $this->visit('/secure');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('secure');
        $this->assertPageContains('Authenticated as admin@example.test');
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the playwright-php/playwright package.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Playwright\Symfony\Tests\Fixtures\App;

use Playwright\Symfony\PlaywrightSymfonyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        // Force debug to false to reduce log noise in tests
        parent::__construct($environment, false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new PlaywrightSymfonyBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test-secret-for-testing',
            'router' => ['utf8' => true],
            'cache' => [
                'directory' => sys_get_temp_dir().'/playwright-symfony-test-cache',
            ],
            'test' => true,
        ]);

        $container->extension('playwright', [
            'enabled' => true,
            'intercepted_hosts' => ['localhost', '127.0.0.1', 'testapp.local'],
            'debug' => false, // Disable debug to reduce log noise
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('hello', '/hello')
            ->controller([Controller\HelloController::class, 'index']);

        $routes->add('echo', '/echo')
            ->controller([Controller\EchoController::class, 'handle'])
            ->methods(['GET', 'POST']);

        $routes->add('redirect_demo', '/redirect')
            ->controller([Controller\RedirectController::class, 'go']);

        $routes->add('big', '/big')
            ->controller([Controller\BigController::class, 'index']);

        $routes->add('binary', '/binary')
            ->controller([Controller\BigController::class, 'binary']);

        $routes->add('cookie', '/cookie')
            ->controller([Controller\CookieController::class, 'show']);

        $routes->add('protected', '/protected')
            ->controller([Controller\ProtectedController::class, 'index']);

        $routes->add('form', '/form')
            ->controller([Controller\FormController::class, 'show'])
            ->methods(['GET', 'POST']);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/playwright-symfony-test-cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/playwright-symfony-test-logs';
    }
}

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

namespace Playwright\Symfony\Tests\Fixtures\App;

use Playwright\Symfony\PlaywrightSymfonyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\Turbo\TurboBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        // Disable debug mode to prevent output that causes risky tests
        parent::__construct($environment, false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new \Symfony\Bundle\TwigBundle\TwigBundle();
        yield new StimulusBundle();
        yield new TwigComponentBundle();
        yield new LiveComponentBundle();
        yield new TurboBundle();
        yield new PlaywrightSymfonyBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $framework = [
            'secret' => 'test-secret-for-testing',
            'router' => [
                'utf8' => true,
                'strict_requirements' => null,
            ],
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
            'cache' => [
                'directory' => $this->tempDir('pools'),
                'app' => 'cache.adapter.filesystem',
                'pools' => [
                    'cache.asset_mapper' => [
                        'adapter' => 'cache.adapter.filesystem',
                    ],
                ],
            ],
            'assets' => [
                'enabled' => true,
            ],
            'property_access' => true,
            'property_info' => true,
            'test' => true,
            'profiler' => [
                'enabled' => true,
                'collect' => false,
            ],
            'asset_mapper' => [
                'server' => true,
                'paths' => [
                    __DIR__.'/assets',
                ],
                'importmap_path' => __DIR__.'/importmap.php',
                'importmap_polyfill' => false,
                'vendor_dir' => __DIR__.'/assets/vendor',
            ],
            'http_client' => true,
        ];

        // framework.type_info only exists from Symfony 7.1
        if (BaseKernel::VERSION_ID >= 70100) {
            $framework['type_info'] = true;
        }

        $container->extension('framework', $framework);

        $container->extension('twig', [
            'default_path' => __DIR__.'/templates',
            'strict_variables' => true,
        ]);

        $container->extension('twig_component', [
            'defaults' => [
                'Playwright\\Symfony\\Tests\\Fixtures\\App\\Component\\' => 'components/',
            ],
            'anonymous_template_directory' => 'components/',
        ]);

        $container->extension('stimulus', [
            'controller_paths' => [__DIR__.'/assets/controllers'],
            'controllers_json' => __DIR__.'/assets/controllers.json',
        ]);

        $container->extension('turbo', [
            'broadcast' => false,
        ]);

        $container->extension('security', [
            'providers' => [
                'test_users' => [
                    'memory' => [
                        'users' => [
                            'admin@example.test' => [
                                'password' => 'unused',
                                'roles' => ['ROLE_ADMIN'],
                            ],
                        ],
                    ],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'test_users',
                    'http_basic' => [],
                ],
            ],
            'access_control' => [
                ['path' => '^/secure$', 'roles' => 'ROLE_ADMIN'],
            ],
        ]);

        // Register controllers as services with autowiring/autoconfiguration
        $services = $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $services
            ->load('Playwright\\Symfony\\Tests\\Fixtures\\App\\Controller\\', __DIR__.'/Controller/*')
            ->public();

        $services
            ->load('Playwright\\Symfony\\Tests\\Fixtures\\App\\Service\\', __DIR__.'/Service/*')
            ->public();

        $services
            ->load('Playwright\\Symfony\\Tests\\Fixtures\\App\\EventListener\\', __DIR__.'/EventListener/*')
            ->public();

        $services
            ->load('Playwright\\Symfony\\Tests\\Fixtures\\App\\Component\\', __DIR__.'/Component/*')
            ->public();

        // Minimal Playwright config for tests
        $container->extension('playwright', [
            'enabled' => true,
            'intercepted_hosts' => ['localhost', '127.0.0.1', 'testapp.local'],
            'debug' => false,
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@LiveComponentBundle/config/routes.php')
            ->prefix('/_components');

        $routes->add('hello', '/hello')
            ->controller([Controller\HelloController::class, 'index']);

        $routes->add('echo', '/echo')
            ->controller([Controller\EchoController::class, 'handle'])
            ->methods(['GET', 'POST']);

        $routes->add('redirect_demo', '/redirect')
            ->controller([Controller\RedirectController::class, 'go']);

        $routes->add('redirect_inspect', '/redirect/inspect')
            ->controller([Controller\RedirectController::class, 'inspect']);

        $routes->add('ux_turbo', '/ux/turbo')
            ->controller([Controller\UxController::class, 'turbo']);

        $routes->add('ux_turbo_redirect', '/ux/turbo/redirect')
            ->controller([Controller\UxController::class, 'turboRedirect']);

        $routes->add('ux_turbo_final', '/ux/turbo/final')
            ->controller([Controller\UxController::class, 'turboFinal']);

        $routes->add('ux_turbo_frame_redirect', '/ux/turbo/frame/redirect')
            ->controller([Controller\UxController::class, 'turboFrameRedirect']);

        $routes->add('ux_turbo_frame_final', '/ux/turbo/frame/final')
            ->controller([Controller\UxController::class, 'turboFrameFinal']);

        $routes->add('ux_live', '/ux/live')
            ->controller([Controller\UxController::class, 'live']);

        $routes->add('ux_live_final', '/ux/live/final')
            ->controller([Controller\UxController::class, 'liveFinal']);

        $routes->add('big', '/big')
            ->controller([Controller\BigController::class, 'index']);

        $routes->add('binary', '/binary')
            ->controller([Controller\BigController::class, 'binary']);

        $routes->add('cookie', '/cookie')
            ->controller([Controller\CookieController::class, 'show']);

        $routes->add('protected', '/protected')
            ->controller([Controller\ProtectedController::class, 'index']);

        $routes->add('secure', '/secure')
            ->controller(Controller\SecureController::class)
            ->methods(['GET']);

        $routes->add('form', '/form')
            ->controller([Controller\FormController::class, 'show'])
            ->methods(['GET', 'POST']);

        $routes->add('page_with_missing_image', '/page-with-missing-image')
            ->controller([Controller\ErrorController::class, 'pageWithMissingImage']);

        $routes->add('missing_image', '/missing-image')
            ->controller([Controller\ErrorController::class, 'missingImage']);

        $routes->add('assetmapper', '/assetmapper')
            ->controller([Controller\AssetMapperController::class, 'demo'])
            ->methods(['GET']);

        $routes->add('assetmapper_trailing', '/assetmapper/')
            ->controller([Controller\AssetMapperController::class, 'demo'])
            ->methods(['GET']);

        $routes->add('twig_demo', '/twig')
            ->controller([Controller\TwigDemoController::class, 'demo'])
            ->methods(['GET']);

        $routes->add('helper_demo', '/helper-demo')
            ->controller(Controller\HelperDemoController::class)
            ->methods(['GET', 'POST']);

        $routes->add('session_set', '/session-set')
            ->controller([Controller\SessionController::class, 'set'])
            ->methods(['GET']);

        $routes->add('session_set_trailing', '/session-set/')
            ->controller([Controller\SessionController::class, 'set'])
            ->methods(['GET']);

        $routes->add('session_get', '/session-get')
            ->controller([Controller\SessionController::class, 'get'])
            ->methods(['GET']);

        $routes->add('session_get_trailing', '/session-get/')
            ->controller([Controller\SessionController::class, 'get'])
            ->methods(['GET']);

        $routes->add('session_clear', '/session-clear')
            ->controller([Controller\SessionController::class, 'clear'])
            ->methods(['GET']);

        $routes->add('session_clear_trailing', '/session-clear/')
            ->controller([Controller\SessionController::class, 'clear'])
            ->methods(['GET']);

        $routes->add('service_users_list', '/service/users')
            ->controller([Controller\ServiceDemoController::class, 'listUsers'])
            ->methods(['GET']);

        $routes->add('service_users_get', '/service/user')
            ->controller([Controller\ServiceDemoController::class, 'getUser'])
            ->methods(['GET']);

        $routes->add('service_users_create', '/service/user')
            ->controller([Controller\ServiceDemoController::class, 'createUser'])
            ->methods(['POST']);

        $routes->add('asset_test', '/asset-test')
            ->controller([Controller\AssetTestController::class, 'demo'])
            ->methods(['GET']);

        // Profile routes
        $routes->add('login', '/login')
            ->controller([Controller\ProfileController::class, 'login'])
            ->methods(['GET']);

        $routes->add('profile', '/profile')
            ->controller([Controller\ProfileController::class, 'profile'])
            ->methods(['GET']);

        $routes->add('profile_trailing', '/profile/')
            ->controller([Controller\ProfileController::class, 'profile'])
            ->methods(['GET']);

        $routes->add('profile_update', '/profile/update')
            ->controller([Controller\ProfileController::class, 'update'])
            ->methods(['POST']);

        // Navigation routes - must be last to act as catch-all
        $routes->add('nav_root', '/')
            ->controller([Controller\NavigationController::class, 'navigate'])
            ->defaults(['path' => '']);

        $routes->add('nav_path_trailing', '/{path}/')
            ->controller([Controller\NavigationController::class, 'navigate'])
            ->requirements(['path' => '[12]+']);

        $routes->add('nav_path', '/{path}')
            ->controller([Controller\NavigationController::class, 'navigate'])
            ->requirements(['path' => '[12]+']);
    }

    public function getCacheDir(): string
    {
        return $this->tempDir('cache');
    }

    public function getLogDir(): string
    {
        return $this->tempDir('logs');
    }

    public function getBuildDir(): string
    {
        return $this->tempDir('build');
    }

    /**
     * Compiled containers belong in the system temp directory, not in the
     * repository. The path is keyed on this checkout so several working copies
     * of the bundle never share an incompatible compiled container.
     */
    private function tempDir(string $name): string
    {
        return sprintf(
            '%s/playwright-symfony-test-%s/%s/%s',
            sys_get_temp_dir(),
            substr(hash('xxh128', __DIR__), 0, 8),
            $name,
            $this->environment,
        );
    }
}

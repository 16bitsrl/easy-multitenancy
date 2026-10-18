<?php

namespace Bit16\EasyMultitenancy\Tests;

use Bit16\EasyMultitenancy\EasyMultitenancyServiceProvider;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Bit16\EasyMultitenancy\Testing\InteractsWithTenants;
use Bit16\EasyMultitenancy\Tests\Fixtures\Post;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Plays the part of an application's own TestCase using the testing trait.
 */
class AppTestCase extends Orchestra
{
    use InteractsWithTenants;

    protected function getPackageProviders($app)
    {
        return [EasyMultitenancyServiceProvider::class];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('easy-multitenancy.database.path', '/nonexistent/app/tenants');

        $app->afterResolving('migrator', fn ($migrator) => $migrator->path(__DIR__.'/Fixtures/migrations'));
    }

    protected function defineRoutes($router)
    {
        $router->middleware('web')->group(function (Router $router) {
            $router->get('posts', fn () => Tenant::current().':'.Post::pluck('title')->join(','))->name('posts.index');
            $router->post('posts', function (Request $request) {
                Post::create(['title' => $request->input('title')]);

                return 'created';
            })->name('posts.store');
        });

        Tenant::centralRoutes(function (Router $router) {
            $router->middleware('web')->get('pricing', fn () => 'pricing:'.(Tenant::current() ?? 'none'));
        });
    }
}

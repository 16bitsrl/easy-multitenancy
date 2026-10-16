<?php

namespace Bit16\EasyMultitenancy\Tests;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Routing\Router;

class RootRouteTestCase extends TestCase
{
    protected function defineRoutes($router)
    {
        parent::defineRoutes($router);

        // An app-defined root route, auto-prefixed to "{tenant}".
        $router->middleware('web')->group(function (Router $router) {
            $router->get('/', fn () => 'app-root:'.(Tenant::current() ?? 'none'))->name('approot');
        });

        // A central route registered after the root, which becomes "{tenant}".
        Tenant::centralRoutes(function (Router $router) {
            $router->middleware('web')->get('about', fn () => 'about:'.(Tenant::current() ?? 'none'))->name('about');
        });
    }
}

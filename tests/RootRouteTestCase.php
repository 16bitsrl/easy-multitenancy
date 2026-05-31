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
    }
}

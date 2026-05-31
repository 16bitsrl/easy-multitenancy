<?php

namespace Bit16\EasyMultitenancy\Tests;

use Bit16\EasyMultitenancy\EasyMultitenancyServiceProvider;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected string $tenantPath;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Bit16\\EasyMultitenancy\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->tenantPath = sys_get_temp_dir().'/easy-multitenancy-tests-'.getmypid().'-'.spl_object_id($this);
        config()->set('easy-multitenancy.database.path', $this->tenantPath);

        if (! is_dir($this->tenantPath)) {
            mkdir($this->tenantPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        Tenant::forget();
        $this->deleteDirectory($this->tenantPath);

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            EasyMultitenancyServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineRoutes($router)
    {
        $router->middleware('web')->group(function (Router $router) {
            $router->get('dashboard', fn () => 'tenant:'.(Tenant::current() ?? 'none'))->name('dashboard');
            $router->get('home', fn () => 'central:'.(Tenant::current() ?? 'none'))->name('home');
            $router->get('login', fn () => 'login')->name('login');
            $router->get('account', fn () => 'account')->middleware('auth')->name('account');
        });

        Tenant::centralRoutes(function (Router $router) {
            $router->middleware('web')->get('pricing', fn () => 'pricing:'.(Tenant::current() ?? 'none'))->name('pricing');
            $router->middleware('web')->get('recent', fn () => Tenant::getRecentTenants())->name('recent');
        });
    }

    /**
     * Create a tenant database file and apply a minimal `posts` schema to it.
     */
    protected function createTenant(string $name): void
    {
        $database = Tenant::getDatabasePath($name);

        if (! is_dir(dirname($database))) {
            mkdir(dirname($database), 0755, true);
        }

        touch($database);

        Tenant::identify($name);

        $connection = config('easy-multitenancy.database.connection', 'tenant');
        Schema::connection($connection)->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        Tenant::forget();
    }

    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}

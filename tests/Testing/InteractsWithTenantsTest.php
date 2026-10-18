<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Bit16\EasyMultitenancy\Tests\Fixtures\Post;
use Bit16\EasyMultitenancy\Tests\Fixtures\Seeders\WelcomePostSeeder;

it('runs the test body and the requests it makes inside the same tenant', function () {
    $this->actingAsTenant('acme');

    Post::create(['title' => 'From the test']);

    $this->get(route('posts.index'))->assertOk()->assertSee('acme:From the test');

    $this->post(route('posts.store'), ['title' => 'From a request'])->assertOk();

    expect(Tenant::current())->toBe('acme')
        ->and(Post::pluck('title')->all())->toBe(['From the test', 'From a request'])
        ->and(route('posts.index', [], false))->toBe('/acme/posts');
});

it('keeps each tenant database separate', function () {
    $this->actingAsTenant('acme');
    Post::create(['title' => 'Acme only']);

    $this->actingAsTenant('globex');

    expect(Post::count())->toBe(0);
    $this->get(route('posts.index'))->assertSee('globex:');
});

it('creates tenant databases in a temporary folder, never in the app tenants folder', function () {
    $this->createTenant('acme');

    expect(Tenant::exists('acme'))->toBeTrue()
        ->and(Tenant::getDatabasePath('acme'))->toStartWith(sys_get_temp_dir())
        ->and(is_dir('/nonexistent/app/tenants'))->toBeFalse();
});

it('migrates tenants and runs the seeders configured for new tenants', function () {
    config()->set('easy-multitenancy.seeders.on_create', [WelcomePostSeeder::class]);

    $this->actingAsTenant('acme');

    expect(Post::pluck('title')->all())->toBe(['Welcome']);
});

it('leaves no tenant active after the test', function () {
    $this->actingAsTenant('acme');
    $path = Tenant::getDatabasePath('acme');

    $this->tearDownInteractsWithTenants();

    expect(Tenant::current())->toBeNull()
        ->and(file_exists($path))->toBeFalse();
});

it('runs central requests without the tenant the test is acting as', function () {
    $this->actingAsTenant('acme');

    $this->get('/pricing')->assertSee('pricing:none');

    expect(Tenant::current())->toBe('acme');
});

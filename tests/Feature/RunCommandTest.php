<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Bit16\EasyMultitenancy\Tests\Fixtures\Post;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::command('posts:add {title}', function (string $title) {
        if (Tenant::current() === 'broken') {
            $this->error('Something went wrong');

            return 1;
        }

        Post::create(['title' => $title]);
        $this->line('added to '.Tenant::current());

        return 0;
    });

    $this->createTenant('acme');
    $this->createTenant('globex');
});

function postsOf(string $tenant): array
{
    Tenant::identify($tenant);
    $titles = Post::pluck('title')->all();
    Tenant::forget();

    return $titles;
}

it('runs an artisan command once in every tenant', function () {
    $this->artisan('tenant:run', ['artisan' => 'posts:add hello'])
        ->expectsOutputToContain('added to acme')
        ->expectsOutputToContain('added to globex')
        ->assertExitCode(0);

    expect(postsOf('acme'))->toBe(['hello'])
        ->and(postsOf('globex'))->toBe(['hello'])
        ->and(Tenant::current())->toBeNull();
});

it('runs only in the tenants given with --tenant', function () {
    $this->artisan('tenant:run', ['artisan' => 'posts:add hello', '--tenant' => ['globex']])
        ->assertExitCode(0);

    expect(postsOf('acme'))->toBe([])
        ->and(postsOf('globex'))->toBe(['hello']);
});

it('keeps going when a tenant fails and reports it at the end', function () {
    $this->createTenant('broken');

    $this->artisan('tenant:run', ['artisan' => 'posts:add hello'])
        ->expectsOutputToContain('Failed in: broken')
        ->assertExitCode(1);

    expect(postsOf('acme'))->toBe(['hello'])
        ->and(postsOf('globex'))->toBe(['hello']);
});

it('refuses unknown tenants before running anything', function () {
    $this->artisan('tenant:run', ['artisan' => 'posts:add hello', '--tenant' => ['acme', 'ghost']])
        ->expectsOutputToContain("Tenant 'ghost' does not exist.")
        ->assertExitCode(1);

    expect(postsOf('acme'))->toBe([]);
});

<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Bit16\EasyMultitenancy\Tests\Fixtures\FailingTenantJob;
use Bit16\EasyMultitenancy\Tests\Fixtures\Post;
use Bit16\EasyMultitenancy\Tests\Fixtures\RecordGlobalJob;
use Bit16\EasyMultitenancy\Tests\Fixtures\RecordTenantJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * End-to-end queue behaviour: jobs are dispatched through the real Laravel
 * queue (database driver on the central connection, left unconfigured as in
 * a stock app) and processed by a real worker.
 */

beforeEach(function () {
    Schema::connection('testing')->create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.connection', null);
    config()->set('queue.failed.driver', 'null');

    $this->createTenant('acme');
});

function work(int $maxJobs = 10): void
{
    Artisan::call('queue:work', [
        'connection' => 'database',
        '--stop-when-empty' => true,
        '--max-jobs' => $maxJobs,
        '--sleep' => 0,
        '--backoff' => 0,
    ]);
}

function centralJobs()
{
    return DB::connection('testing')->table('jobs');
}

it('queues tenant jobs on the central connection with the tenant id', function () {
    $this->get('/acme/jobs/dispatch')->assertOk();

    expect(centralJobs()->count())->toBe(1);

    $payload = json_decode(centralJobs()->first()->payload, true);
    expect($payload['tenant_id'])->toBe('acme');
});

it('runs a queued job inside the tenant it was dispatched from', function () {
    $this->get('/acme/jobs/dispatch')->assertOk();

    work();

    expect(RecordTenantJob::$seen)->toBe(['acme'])
        ->and(Tenant::current())->toBeNull();

    Tenant::identify('acme');
    expect(Post::where('title', 'from-job')->count())->toBe(1);
});

it('does not leak a tenant into the next job after a released attempt', function () {
    Tenant::identify('acme');
    FailingTenantJob::dispatch();
    Tenant::forget();
    RecordGlobalJob::dispatch();

    work(maxJobs: 2);

    expect(RecordGlobalJob::$seen)->toBe([null]);
});

it('fails a job whose tenant no longer exists in strict mode', function () {
    config()->set('easy-multitenancy.queue.strict_mode', true);

    $this->get('/acme/jobs/dispatch')->assertOk();
    unlink(Tenant::getDatabasePath('acme'));

    work();

    expect(RecordTenantJob::$seen)->toBe([])
        ->and(centralJobs()->count())->toBe(0);
});

it('runs a job for a missing tenant without tenant context when not strict', function () {
    config()->set('easy-multitenancy.queue.strict_mode', false);

    $this->get('/acme/jobs/dispatch')->assertOk();
    unlink(Tenant::getDatabasePath('acme'));

    work();

    expect(RecordTenantJob::$seen)->toBe([null]);
});

it('keeps the request tenant after running sync jobs', function () {
    config()->set('queue.default', 'sync');

    $this->get('/acme/jobs/sync')->assertOk()->assertSee('after:acme');

    expect(RecordTenantJob::$seen)->toBe(['acme'])
        ->and(RecordGlobalJob::$seen)->toBe([null]);
});

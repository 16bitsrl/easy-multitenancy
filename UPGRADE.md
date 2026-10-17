# Upgrade guide

## From 0.x to 1.0

Start by running the upgrade check in your application. It only reads your code and data, and lists
what still needs changing (it exits with a failure code until the list is empty, so it can also run
in CI):

```bash
php artisan tenant:upgrade-check
```

Use `--path=` (repeatable) to scan directories other than `app`, `routes` and `resources/views`.

### Requirements

Laravel 13 and PHP 8.4 or later.

### Route actions no longer receive the tenant

Tenant routes used to pass the tenant as the first argument to the controller or closure, because
Laravel passes route parameters by position. Actions now receive only their own parameters, and
route model binding runs against the tenant database:

```php
// Before
Route::get('posts/{post}', fn ($tenant, $post) => /* ... */);

// After
Route::get('posts/{post}', fn (Post $post) => /* ... */);
```

Wherever you need the tenant, use `Tenant::current()`. `$request->route('tenant')` now returns `null`.

### Tenant identifiers

Identifiers must be 2-63 characters of lowercase letters, numbers and single hyphens, not starting
or ending with a hyphen (`acme`, `acme-corp`). A tenant database with another name (for example
`-acme.sqlite` or `a.sqlite`) is no longer listed or reachable: rename the file to a valid
identifier. The upgrade check lists them.

`tenant:create` also rejects names that collide with paths the app already serves (`api`, `up`,
`horizon`, the first segment of a central route, ...).

### Storage

Storage isolation no longer switches the default disk to a `tenant` disk. Every disk listed in
`storage.disks` (default `local` and `public`) gets a `tenants/{tenant}` folder, and remote disks
such as S3 a `tenants/{tenant}` object prefix:

```php
'storage' => [
    'suffix_enabled' => env('TENANT_STORAGE_SUFFIX', true),
    'path' => env('TENANT_STORAGE_PATH', 'tenants'),
    'disks' => ['local', 'public', 's3'],
],
```

- Replace `Storage::disk('tenant')` with the disk you mean (`Storage::disk('local')`, or the default).
- Move existing files from `storage/app/tenants` into the `tenants` folder of the local disk
  (`storage/app/private/tenants` on a default Laravel 13 app). The upgrade check prints the exact
  paths.
- If you published the config file, add the `disks` key.

### Queue internals

If you call `QueueTenantInjector` yourself, `shouldRestoreTenant()`, `restoreTenant()` and
`cleanupTenant()` are replaced by `beginJob($job)` and `endJob($job)`. Apps that only dispatch jobs
need no change.

Note the behaviour changes:

- with `queue.strict_mode` (default on), a job whose tenant no longer exists is now marked as failed
  instead of running without a tenant;
- queues on the `database` driver always store jobs on the central connection.

### Central home

The default central home is now in English and follows the application locale (English and Italian
are included). To change the texts, publish the translations:

```bash
php artisan vendor:publish --tag="easy-multitenancy-translations"
```

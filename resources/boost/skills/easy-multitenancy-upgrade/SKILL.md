---
name: easy-multitenancy-upgrade
description: >
  Upgrade a Laravel app from 16bit/easy-multitenancy 0.x to 1.0. Use when bumping the package to ^1.0,
  when tenant routes start receiving the wrong arguments after the upgrade, when tenant files or
  databases seem missing, or when asked to make an app compatible with easy-multitenancy v1.
compatibility: Laravel 13, PHP 8.4+.
---

# Upgrading easy-multitenancy to 1.0

Work through these steps in order and re-run the check until it passes. Do not rename tenant databases
or move files without telling the user first: those are their production data.

## 1. Update the dependency

```bash
composer require 16bit/easy-multitenancy:^1.0
```

v1 requires Laravel 13 and PHP 8.4+. If the config is published, compare it with the package's
`config/easy-multitenancy.php` and add the new `storage.disks` key.

## 2. Run the upgrade check

```bash
php artisan tenant:upgrade-check
```

It only reads. It lists, with file and line, everything below and exits with code 1 until nothing is
left. Use `--path=` (repeatable) for code outside `app`, `routes` and `resources/views`.

## 3. Fix each kind of finding

**Route actions that still expect the tenant argument.** Remove the `$tenant` parameter. Where the code
used it, call `Tenant::current()` instead.

```php
// before
public function show($tenant, Invoice $invoice) { ... $tenant ... }
// after
public function show(Invoice $invoice) { ... Tenant::current() ... }
```

Also check Livewire `mount()` methods and closures that took the tenant first.

**Code that reads the tenant from the route** (`$request->route('tenant')`, `->parameter('tenant')`):
replace with `Tenant::current()`.

**The removed `tenant` disk** (`Storage::disk('tenant')`): use the disk the files belong to
(`Storage::disk('local')` or the default disk). Disks listed in `storage.disks` are tenant-scoped.

**Removed `QueueTenantInjector` methods** (`shouldRestoreTenant`, `restoreTenant`, `cleanupTenant`): use
`beginJob($job)` / `endJob($job)`, or drop the custom code, since the package handles the job lifecycle.

**Tenant databases with invalid names**: identifiers must now be 2-63 characters of lowercase letters,
numbers and single hyphens. Propose a new name to the user, then rename the `.sqlite` file and update
anything that stores the old name (links, central records).

**Tenant files in the previous storage location**: files moved from `storage/app/tenants/{tenant}` to a
`tenants/{tenant}` folder in each disk (`storage/app/private/tenants/{tenant}` for the default local
disk). Move them with the command the check prints, and move files meant to be public to the public
disk's `tenants` folder.

## 4. Behaviour changes to review

- `queue.strict_mode` (default on) now fails jobs whose tenant no longer exists instead of running them.
- Queues on the `database` driver always use the central connection.
- The default central home is in English and follows the app locale.
- `Tenant::sanitize()` turns spaces and underscores into hyphens.

## 5. Verify

```bash
php artisan tenant:upgrade-check   # must report nothing to upgrade
php artisan test
```

Then open two tenants in the browser: log in on one and check you are a guest on the other, and that
records, uploads and links stay within each tenant.

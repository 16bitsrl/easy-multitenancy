<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Lists what an application has to change when upgrading to v1. It only
 * reads: nothing is modified. Exits with a failure code while anything is
 * left to fix, so it can run in CI.
 */
class UpgradeCheckCommand extends Command
{
    protected $signature = 'tenant:upgrade-check
        {--path=* : Directories to scan for code (default: app, routes, resources/views)}';

    protected $description = 'Find code and data that need changes for easy-multitenancy v1';

    /** @var array<string, array<int, string>> */
    protected array $findings = [];

    public function handle(): int
    {
        $this->checkRouteActions();
        $this->checkSourceCode();
        $this->checkTenantNames();
        $this->checkStorageLocation();

        if ($this->findings === []) {
            $this->components->info('Nothing to upgrade: this application is ready for easy-multitenancy v1.');

            return self::SUCCESS;
        }

        foreach ($this->findings as $section => $items) {
            $this->components->warn($section);

            // Plain lines: paths and commands must stay copyable, unwrapped.
            foreach ($items as $item) {
                $this->line('  • '.$item);
            }

            $this->newLine();
        }

        $this->components->error('Fix the items above, then run this command again.');

        return self::FAILURE;
    }

    /**
     * Tenant routes no longer pass the tenant to the action, so an action
     * declaring it would receive the next route parameter in its place.
     */
    protected function checkRouteActions(): void
    {
        $parameter = config('easy-multitenancy.routes.parameter', 'tenant');

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $names = $route->parameterNames();

            if (! in_array($parameter, $names, true) || ! ($function = $this->reflectAction($route))) {
                continue;
            }

            $scalar = array_filter($function->getParameters(), function ($argument) {
                $type = $argument->getType();

                return ! ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) && ! $argument->isVariadic();
            });

            $declaresTenant = in_array($parameter, array_map(fn ($argument) => $argument->getName(), $scalar), true);

            if ($declaresTenant || count($scalar) > count($names) - 1) {
                $this->findings['Route actions that still expect the tenant argument (remove it and use Tenant::current())'][] =
                    "{$route->uri()} → {$this->describeAction($route, $function)} declares \${$parameter}";
            }
        }
    }

    protected function reflectAction(Route $route): ?ReflectionFunctionAbstract
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof Closure) {
            return new ReflectionFunction($uses);
        }

        if (is_string($uses) && str_contains($uses, '@')) {
            [$class, $method] = explode('@', $uses, 2);

            if (method_exists($class, $method)) {
                return new ReflectionMethod($class, $method);
            }
        }

        return null;
    }

    protected function describeAction(Route $route, ReflectionFunctionAbstract $function): string
    {
        if ($function instanceof ReflectionMethod) {
            return $function->class.'@'.$function->getName();
        }

        return 'closure in '.$this->relativePath((string) $function->getFileName()).':'.$function->getStartLine();
    }

    protected function checkSourceCode(): void
    {
        $parameter = preg_quote(config('easy-multitenancy.routes.parameter', 'tenant'), '/');

        $patterns = [
            "/route\\(\\s*['\"]{$parameter}['\"]\\s*\\)|->parameter\\(\\s*['\"]{$parameter}['\"]/" => 'reads the tenant from the route: use Tenant::current()',
            "/disk\\(\\s*['\"]tenant['\"]\\s*\\)/" => 'uses the removed "tenant" disk: use the disks listed in storage.disks, they are tenant-scoped',
            '/\b(shouldRestoreTenant|restoreTenant|cleanupTenant)\s*\(/' => 'calls a removed QueueTenantInjector method: use beginJob() / endJob()',
        ];

        foreach ($this->scanPaths() as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                foreach (file($file->getPathname()) ?: [] as $index => $line) {
                    foreach ($patterns as $pattern => $message) {
                        if (preg_match($pattern, $line)) {
                            $this->findings['Code to update'][] = $this->relativePath($file->getPathname()).':'.($index + 1).' '.$message;
                        }
                    }
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function scanPaths(): array
    {
        $paths = $this->option('path') ?: [base_path('app'), base_path('routes'), resource_path('views')];

        return array_values(array_filter($paths, 'is_dir'));
    }

    protected function checkTenantNames(): void
    {
        $path = config('easy-multitenancy.database.path', database_path('tenants'));
        $extension = config('easy-multitenancy.database.extension', '.sqlite');

        foreach (glob($path.'/*'.$extension) ?: [] as $file) {
            $name = basename($file, $extension);

            if (! Tenant::isValid($name)) {
                $this->findings['Tenant databases with a name that is no longer valid (rename the file to a valid identifier)'][] =
                    "{$name} ({$file})";
            }
        }
    }

    /**
     * Tenant files used to live in storage/app/{path}/{tenant}; they now live
     * in a tenant folder inside each scoped disk.
     */
    protected function checkStorageLocation(): void
    {
        $folder = trim(config('easy-multitenancy.storage.path', 'tenants'), '/');
        $old = storage_path('app/'.$folder);
        $localRoot = config('filesystems.disks.local.root', storage_path('app/private'));
        $new = rtrim($localRoot, '/').'/'.$folder;

        if (! is_dir($old) || realpath($old) === realpath($new) || File::allFiles($old) === []) {
            return;
        }

        $this->findings['Tenant files in the previous storage location'][] =
            "Move the contents of {$old} to {$new} (e.g. rsync -a {$old}/ {$new}/), then remove the old folder once checked. Files meant to be public belong in the {$folder} folder of the public disk.";
    }

    protected function relativePath(string $path): string
    {
        return str_starts_with($path, base_path().'/') ? substr($path, strlen(base_path()) + 1) : $path;
    }
}

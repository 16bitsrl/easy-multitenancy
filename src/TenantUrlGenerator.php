<?php

namespace Bit16\EasyMultitenancy;

use Illuminate\Routing\UrlGenerator;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class TenantUrlGenerator extends UrlGenerator
{
    public function to($path, $extra = [], $secure = null)
    {
        $tenant = app('tenant')->current();

        if ($tenant && !str_starts_with($path, 'http')) {
            if (str_starts_with($path, '/')) {
                if (!str_starts_with($path, '/' . $tenant)) {
                    $path = '/' . $tenant . $path;
                }
            } else {
                $path = $tenant . '/' . $path;
            }
        }

        return parent::to($path, $extra, $secure);
    }

    public function getDefaultParameters()
    {
        $defaults = parent::getDefaultParameters();

        $tenant = app('tenant')->current();
        if ($tenant && !isset($defaults['tenant'])) {
            $defaults['tenant'] = $tenant;
        }

        return $defaults;
    }

    public function route($name, $parameters = [], $absolute = true)
    {
        if (!is_null($route = $this->routes->getByName($name))) {
            $tenant = app('tenant')->current();

            $parametersArray = is_array($parameters) ? $parameters : [$parameters];

            if (!isset($parametersArray['tenant']) && str_contains($route->uri(), '{tenant}')) {
                if ($tenant) {
                    $parametersArray = array_merge(['tenant' => $tenant], $parametersArray);
                }
            }

            return $this->toRoute($route, $parametersArray, $absolute);
        }

        throw new RouteNotFoundException("Route [{$name}] not defined.");
    }
}

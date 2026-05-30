<?php

use Bit16\EasyMultitenancy\Traits\ChecksRouteExclusions;
use Illuminate\Routing\Route;

beforeEach(function () {
    $this->trait = new class () {
        use ChecksRouteExclusions;

        public function testIsRouteExcluded($route, $name, $uri, $excludedRoutes, $excludedPatterns)
        {
            return $this->isRouteExcluded($route, $name, $uri, $excludedRoutes, $excludedPatterns);
        }

        public function testParseUriPath($uri)
        {
            return $this->parseUriPath($uri);
        }
    };

    $this->excludedRoutes = ['home'];
    $this->excludedPatterns = [
        'up',
        'horizon*',
        'telescope*',
        'api/*',
        '_debugbar/*',
        '*.js',
        '*.css',
        '*.map',
    ];
});

it('excludes routes by exact name match', function () {
    expect($this->trait->testIsRouteExcluded(null, 'home', null, $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, 'dashboard', null, $this->excludedRoutes, $this->excludedPatterns))->toBeFalse();
});

it('excludes routes matching wildcard patterns by name', function () {
    expect($this->trait->testIsRouteExcluded(null, 'horizon', null, $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, 'horizon.index', null, $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, 'telescope.requests', null, $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
});

it('excludes routes matching wildcard patterns by URI', function () {
    expect($this->trait->testIsRouteExcluded(null, null, 'api/users', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'api/posts', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'horizon/jobs', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, '_debugbar/open', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'users', $this->excludedRoutes, $this->excludedPatterns))->toBeFalse();
});

it('excludes routes matching file extension patterns', function () {
    expect($this->trait->testIsRouteExcluded(null, null, 'app.js', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'vendor.js', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'app.css', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'app.js.map', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'app.min.js', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
});

it('strips query parameters before pattern matching', function () {
    expect($this->trait->testIsRouteExcluded(null, null, 'api/users?page=1', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'horizon.js?v=1', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'app.css?version=2', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
});

it('strips fragments before pattern matching', function () {
    expect($this->trait->testIsRouteExcluded(null, null, 'app.css#section', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'api/users#top', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
});

it('strips both query parameters and fragments before pattern matching', function () {
    expect($this->trait->testIsRouteExcluded(null, null, 'api/users?page=1#top', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
    expect($this->trait->testIsRouteExcluded(null, null, 'app.js?v=1#bundle', $this->excludedRoutes, $this->excludedPatterns))->toBeTrue();
});

it('does not exclude routes that already have tenant prefix', function () {
    $route = new Route(['GET'], '{tenant}/api/users', []);

    expect($this->trait->testIsRouteExcluded($route, null, null, $this->excludedRoutes, $this->excludedPatterns))->toBeFalse();
    expect($this->trait->testIsRouteExcluded(null, null, '{tenant}/api/users', $this->excludedRoutes, $this->excludedPatterns))->toBeFalse();
});

it('correctly parses URI path stripping query params and fragments', function () {
    expect($this->trait->testParseUriPath('api/users?page=1'))->toBe('api/users');
    expect($this->trait->testParseUriPath('horizon.js?v=1'))->toBe('horizon.js');
    expect($this->trait->testParseUriPath('app.css#section'))->toBe('app.css');
    expect($this->trait->testParseUriPath('api/users?page=1#top'))->toBe('api/users');
    expect($this->trait->testParseUriPath('simple/path'))->toBe('simple/path');
});

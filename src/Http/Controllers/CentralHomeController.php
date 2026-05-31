<?php

namespace Bit16\EasyMultitenancy\Http\Controllers;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Default central (landlord) home: a tenant picker placeholder shown when the
 * host application has not defined its own `/` central route. Override it by
 * registering your own central `/` route, or publish and edit the view.
 */
class CentralHomeController
{
    public function show(Request $request)
    {
        $recentTenants = collect(Tenant::getRecentTenants())
            ->map(fn ($timestamp, $name) => [
                'name' => $name,
                'url' => url('/'.$name),
                'when' => Carbon::createFromTimestamp($timestamp)->diffForHumans(),
            ])
            ->values()
            ->all();

        return view('easy-multitenancy::central-home', [
            'appName' => config('app.name', 'Laravel'),
            'recentTenants' => $recentTenants,
            'error' => session('error'),
            'prefill' => old('tenant'),
        ]);
    }

    public function submit(Request $request)
    {
        $candidate = strtolower(trim((string) $request->input('tenant')));

        if ($candidate !== '' && Tenant::isValid($candidate) && Tenant::exists($candidate)) {
            return redirect('/'.$candidate);
        }

        return redirect('/')->withInput()->with('error', 'Tenant non trovato.');
    }
}

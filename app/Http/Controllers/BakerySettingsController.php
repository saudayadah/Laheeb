<?php

namespace App\Http\Controllers;

use App\Http\Requests\BakerySettingsRequest;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BakerySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $all = AppSettings::all();

        return Inertia::render('settings/bakery', [
            'settings' => [
                'bakery_name_ar' => $all['bakery_name_ar'],
                'bakery_name_en' => $all['bakery_name_en'],
                'vat_number' => $all['vat_number'],
                'cr_number' => $all['cr_number'],
                'national_address' => $all['national_address'],
                'phone' => $all['phone'],
                'vat_enabled' => (bool) $all['vat_enabled'],
                'prices_include_vat' => (bool) $all['prices_include_vat'],
                'vat_rate' => (string) $all['vat_rate'],
                'default_credit_days' => (int) $all['default_credit_days'],
            ],
        ]);
    }

    public function update(BakerySettingsRequest $request): RedirectResponse
    {
        $old = AppSettings::all();
        $new = $request->validated();

        AppSettings::setMany($new);

        activity()
            ->causedBy($request->user())
            ->withProperties(['old' => array_intersect_key($old, $new), 'attributes' => $new])
            ->log('settings.updated');

        return back()->with('success', __('common.saved'));
    }
}

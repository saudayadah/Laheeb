<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriceRequest;
use App\Models\Price;
use Illuminate\Http\RedirectResponse;

class PriceController extends Controller
{
    public function store(PriceRequest $request): RedirectResponse
    {
        $this->authorize('create', Price::class);

        Price::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', __('common.saved'));
    }

    public function destroy(Price $price): RedirectResponse
    {
        $this->authorize('delete', $price);

        $price->delete();

        return back()->with('success', __('common.deleted'));
    }
}

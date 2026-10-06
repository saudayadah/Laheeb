<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LocaleController extends Controller
{
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:ar,en'],
        ]);

        $request->session()->put('locale', $validated['locale']);

        $request->user()?->forceFill(['locale' => $validated['locale']])->save();

        // Full page reload so the document direction (RTL/LTR) is re-applied.
        return Inertia::location(url()->previous() ?: route('dashboard'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ReceivablesService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceivablesController extends Controller
{
    public function index(Request $request, ReceivablesService $service): Response
    {
        abort_unless($request->user()->can('balances.view'), 403);

        $aging = $service->aging();

        return Inertia::render('receivables/index', [
            'rows' => $aging['rows'],
            'totals' => $aging['totals'],
            'canReceipt' => $request->user()->can('receipts.manage'),
        ]);
    }
}

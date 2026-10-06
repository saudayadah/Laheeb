<?php

namespace App\Http\Controllers;

use App\Exports\TemplateExport;
use App\Imports\RowsImport;
use App\Services\Imports\ImporterRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('imports.run'), 403);

        return Inertia::render('imports/index', [
            'types' => ImporterRegistry::types(),
        ]);
    }

    public function template(Request $request, string $type): BinaryFileResponse
    {
        abort_unless($request->user()->can('imports.run'), 403);

        $importer = ImporterRegistry::make($type);

        return Excel::download(
            new TemplateExport($importer->headings(), $importer->exampleRows()),
            "laheeb-{$type}-template.xlsx",
        );
    }

    public function preview(Request $request, string $type): Response|RedirectResponse
    {
        abort_unless($request->user()->can('imports.run'), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $importer = ImporterRegistry::make($type);

        $token = Str::uuid()->toString();
        $path = $request->file('file')->storeAs('imports', "{$token}.{$request->file('file')->getClientOriginalExtension()}");

        try {
            $rowsImport = new RowsImport;
            Excel::import($rowsImport, Storage::path($path));
            $result = $importer->validateRows($rowsImport->rows, $request->user());
        } catch (Throwable) {
            Storage::delete($path);

            return redirect()->route('imports.index')->with('error', __('imports.file_error'));
        }

        Cache::put("import.{$token}", ['type' => $type, 'path' => $path], now()->addMinutes(30));

        return Inertia::render('imports/preview', [
            'type' => $type,
            'token' => $token,
            'headings' => $importer->headings(),
            'rows' => $result,
            'validCount' => count(array_filter($result, fn ($r) => $r['errors'] === [])),
            'errorCount' => count(array_filter($result, fn ($r) => $r['errors'] !== [])),
        ]);
    }

    public function commit(Request $request, string $type): RedirectResponse
    {
        abort_unless($request->user()->can('imports.run'), 403);

        $request->validate(['token' => ['required', 'uuid']]);

        $token = $request->string('token')->toString();
        $stored = Cache::get("import.{$token}");

        if ($stored === null || $stored['type'] !== $type) {
            return redirect()->route('imports.index')->with('error', __('imports.file_error'));
        }

        $importer = ImporterRegistry::make($type);

        try {
            $rowsImport = new RowsImport;
            Excel::import($rowsImport, Storage::path($stored['path']));
            $result = $importer->validateRows($rowsImport->rows, $request->user());
        } catch (Throwable) {
            return redirect()->route('imports.index')->with('error', __('imports.file_error'));
        }

        $validRows = array_values(array_filter($result, fn ($r) => $r['errors'] === []));

        if ($validRows === []) {
            return redirect()->route('imports.index')->with('error', __('imports.nothing_to_import'));
        }

        $count = DB::transaction(fn () => $importer->commit($validRows, $request->user()));

        activity()
            ->causedBy($request->user())
            ->withProperties(['type' => $type, 'count' => $count])
            ->log('import.committed');

        Cache::forget("import.{$token}");
        Storage::delete($stored['path']);

        return redirect()->route('imports.index')
            ->with('success', __('imports.done', ['count' => $count]));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\ReportService;
use App\Support\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class StatementController extends Controller
{
    public function __construct(private ReportService $service) {}

    /** Office statement with the share controls. */
    public function show(Request $request, Customer $customer): Response
    {
        abort_unless($request->user()->can('balances.view'), 403);

        [$from, $to] = $this->range($request);
        $statement = $this->service->statement($customer, $from, $to);

        // Time-limited public link + a prefilled Arabic WhatsApp message.
        $publicUrl = URL::temporarySignedRoute('public.statement', now()->addDays(7), [
            'customer' => $customer->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        $waText = __('statements.wa_message', [
            'name' => $customer->name,
            'balance' => $statement['closing'],
            'link' => $publicUrl,
        ]);

        $waPhone = preg_replace('/\D/', '', (string) ($customer->whatsapp ?: $customer->phone));
        if (str_starts_with($waPhone, '05')) {
            $waPhone = '966'.substr($waPhone, 1);
        }

        return Inertia::render('statements/show', [
            'customer' => $customer->only(['id', 'code', 'name', 'phone', 'whatsapp', 'vat_number']),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'statement' => $statement,
            'bakeryName' => (string) AppSettings::get('bakery_name_ar'),
            'publicUrl' => $publicUrl,
            'waUrl' => 'https://wa.me/'.($waPhone ?: '').'?text='.rawurlencode($waText),
            'isPublic' => false,
        ]);
    }

    /** The same statement, reachable by the customer through a signed link. */
    public function publicShow(Request $request, Customer $customer): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        [$from, $to] = $this->range($request);

        return Inertia::render('statements/show', [
            'customer' => $customer->only(['id', 'code', 'name', 'vat_number']),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'statement' => $this->service->statement($customer, $from, $to),
            'bakeryName' => (string) AppSettings::get('bakery_name_ar'),
            'publicUrl' => null,
            'waUrl' => null,
            'isPublic' => true,
        ]);
    }

    /** Server-rendered Arabic PDF (mPDF — correct shaping and RTL). */
    public function pdf(Request $request, Customer $customer): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($request->user()->can('balances.view'), 403);

        [$from, $to] = $this->range($request);

        $html = view('pdf.statement', [
            'customer' => $customer,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'statement' => $this->service->statement($customer, $from, $to),
            'bakeryName' => (string) AppSettings::get('bakery_name_ar'),
        ])->render();

        // Production: inside storage. Local dev: the system temp dir, because
        // OneDrive marks freshly created folders read-only and breaks mPDF.
        $tempDir = app()->environment('production')
            ? storage_path('app/mpdf')
            : rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'laheeb-mpdf';
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);
        $mpdf->SetTitle("كشف حساب {$customer->name}");
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="statement-'.$customer->code.'-'.$to->toDateString().'.pdf"',
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        try {
            $from = Carbon::parse($request->input('from', today()->subMonths(3)->startOfMonth()->toDateString()));
            $to = Carbon::parse($request->input('to', today()->toDateString()));
        } catch (\Throwable) {
            [$from, $to] = [today()->subMonths(3), today()];
        }

        return [$from, $to];
    }
}

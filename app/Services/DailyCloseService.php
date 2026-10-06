<?php

namespace App\Services;

use App\Models\CashHandover;
use App\Models\DailyClose;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyCloseService
{
    public function __construct(private PostingService $posting) {}

    /**
     * Expected cash in hand for a driver on a date:
     * cash invoices + cash collections - cash expenses (ledger movement of the day),
     * excluding handovers themselves.
     */
    public function expectedForDriver(int $driverId, Carbon $date): string
    {
        $row = LedgerEntry::query()
            ->where('account_type', LedgerEntry::DRIVER)
            ->where('account_id', $driverId)
            ->whereDate('entry_date', $date)
            ->where(fn ($q) => $q->whereNull('source_type')->orWhere('source_type', '!=', CashHandover::class))
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        return Money::subtract((string) $row->d, (string) $row->c);
    }

    /** Expected cash in the counter box on a date (includes driver handovers). */
    public function expectedForCounter(Carbon $date): string
    {
        $row = LedgerEntry::query()
            ->where('account_type', LedgerEntry::CASH_BOX)
            ->where('account_id', LedgerEntry::MAIN_CASH_BOX_ID)
            ->whereDate('entry_date', $date)
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        return Money::subtract((string) $row->d, (string) $row->c);
    }

    /** What makes up the expected amount — shown to the person counting. */
    public function breakdown(string $type, int $id, Carbon $date): array
    {
        if ($type === DailyClose::TYPE_DRIVER) {
            $cashSales = Invoice::whereDate('invoice_date', $date)
                ->where('driver_id', $id)
                ->where('payment_method', 'cash')
                ->where('status', Invoice::STATUS_POSTED)
                ->sum('total');

            $collections = Receipt::whereDate('receipt_date', $date)
                ->where('received_by', $id)
                ->where('method', 'cash')
                ->where('status', 'posted')
                ->sum('amount');

            $expenses = LedgerEntry::query()
                ->where('account_type', LedgerEntry::DRIVER)
                ->where('account_id', $id)
                ->whereDate('entry_date', $date)
                ->where(fn ($q) => $q->whereNull('source_type')->orWhere('source_type', '!=', CashHandover::class))
                ->where('credit', '>', 0)
                ->whereNotIn('source_type', [Receipt::class, Invoice::class])
                ->sum('credit');

            return [
                'cash_sales' => (string) $cashSales,
                'collections' => (string) $collections,
                'expenses' => (string) $expenses,
            ];
        }

        $cashSales = Invoice::whereDate('invoice_date', $date)
            ->whereNull('driver_id')
            ->where('payment_method', 'cash')
            ->where('status', Invoice::STATUS_POSTED)
            ->sum('total');

        $handovers = CashHandover::whereDate('handover_date', $date)->sum('amount');

        $expenses = LedgerEntry::query()
            ->where('account_type', LedgerEntry::CASH_BOX)
            ->where('account_id', LedgerEntry::MAIN_CASH_BOX_ID)
            ->whereDate('entry_date', $date)
            ->where('credit', '>', 0)
            ->sum('credit');

        return [
            'cash_sales' => (string) $cashSales,
            'collections' => (string) $handovers, // shown as "received from drivers"
            'expenses' => (string) $expenses,
        ];
    }

    /**
     * Submit (or re-submit) a day count. @param array<int,int> $denominations [denomination => count]
     */
    public function submit(string $type, int $id, Carbon $date, array $denominations, ?string $notes, User $user): DailyClose
    {
        return DB::transaction(function () use ($type, $id, $date, $denominations, $notes, $user) {
            $existing = DailyClose::whereDate('close_date', $date)
                ->where('closeable_type', $type)
                ->where('closeable_id', $id)
                ->lockForUpdate()
                ->first();

            if ($existing?->isApproved()) {
                throw ValidationException::withMessages(['close' => __('closes.already_approved')]);
            }

            $counted = '0.00';
            foreach (DailyClose::DENOMINATIONS as $denomination) {
                $count = (int) ($denominations[$denomination] ?? 0);
                $counted = Money::add($counted, Money::line($count, (string) $denomination));
            }

            $expected = $type === DailyClose::TYPE_DRIVER
                ? $this->expectedForDriver($id, $date)
                : $this->expectedForCounter($date);

            $close = DailyClose::updateOrCreate(
                ['close_date' => $date->startOfDay(), 'closeable_type' => $type, 'closeable_id' => $id],
                [
                    'expected' => $expected,
                    'counted' => $counted,
                    'variance' => Money::subtract($counted, $expected),
                    'status' => 'pending',
                    'submitted_by' => $user->id,
                    'notes' => $notes,
                ],
            );

            $close->denominations()->delete();
            foreach (DailyClose::DENOMINATIONS as $denomination) {
                $close->denominations()->create([
                    'denomination' => $denomination,
                    'count' => (int) ($denominations[$denomination] ?? 0),
                ]);
            }

            return $close->refresh();
        });
    }

    /**
     * Approve: the counted cash moves from the driver's custody to the box,
     * any shortage simply stays on the driver's custody balance, and the
     * driver's day is locked.
     */
    public function approve(DailyClose $close, User $approver): DailyClose
    {
        return DB::transaction(function () use ($close, $approver) {
            $close = DailyClose::whereKey($close->id)->lockForUpdate()->firstOrFail();

            if ($close->isApproved()) {
                return $close;
            }

            if ($close->closeable_type === DailyClose::TYPE_DRIVER && Money::compare((string) $close->counted, '0.00') > 0) {
                $handover = CashHandover::create([
                    'driver_id' => $close->closeable_id,
                    'handover_date' => $close->close_date,
                    'amount' => $close->counted,
                    'received_by' => $approver->id,
                    'daily_close_id' => $close->id,
                ]);

                $this->posting->entry(
                    LedgerEntry::DRIVER, (int) $close->closeable_id, $close->close_date,
                    debit: '0.00', credit: (string) $close->counted,
                    source: $handover,
                    description: __('ledger.handover', ['date' => $close->close_date->toDateString()]),
                    userId: $approver->id,
                );
                $this->posting->entry(
                    LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID, $close->close_date,
                    debit: (string) $close->counted, credit: '0.00',
                    source: $handover,
                    description: __('ledger.handover', ['date' => $close->close_date->toDateString()]),
                    userId: $approver->id,
                );
            }

            $close->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            activity()->causedBy($approver)->performedOn($close)->log('close.approved');

            return $close;
        });
    }
}

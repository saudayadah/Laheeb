<?php

namespace App\Console\Commands;

use App\Models\Advance;
use App\Models\CashHandover;
use App\Models\CreditNote;
use App\Models\EmployeeCharge;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\PayrollLine;
use App\Models\Receipt;
use App\Models\SupplierPayment;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Cross-checks every money document against its ledger entries.
 * Exit code 0 = the books balance; 1 = discrepancies listed below.
 */
class VerifyLedger extends Command
{
    protected $signature = 'ledger:verify';

    protected $description = 'Verify that every posted document matches its ledger entries and line totals';

    private int $problems = 0;

    public function handle(): int
    {
        $this->checkInvoices();
        $this->checkCreditNotes();
        $this->checkReceipts();
        $this->checkExpenses();
        $this->checkAdvances();
        $this->checkCharges();
        $this->checkSimple(CashHandover::class, 'amount');
        $this->checkSimple(SupplierPayment::class, 'amount');
        $this->checkPayrollRecoveries();
        $this->checkOrphans();

        if ($this->problems === 0) {
            $this->info('Ledger verified: every document matches its entries. ✔');

            return self::SUCCESS;
        }

        $this->error("{$this->problems} discrepancies found.");

        return self::FAILURE;
    }

    private function net(Model $doc): string
    {
        $row = LedgerEntry::query()
            ->where('source_type', $doc->getMorphClass())
            ->where('source_id', $doc->getKey())
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        return Money::subtract((string) $row->d, (string) $row->c);
    }

    private function expect(Model $doc, string $label, string $expected, string $actual): void
    {
        if (Money::compare($expected, $actual) !== 0) {
            $this->problems++;
            $this->warn(sprintf('%s #%d: %s expected %s, found %s', class_basename($doc), $doc->getKey(), $label, $expected, $actual));
        }
    }

    private function checkInvoices(): void
    {
        Invoice::query()->whereIn('status', [Invoice::STATUS_POSTED, Invoice::STATUS_VOID])
            ->withSum('lines as lines_total', 'line_total')
            ->chunkById(200, function ($invoices) {
                foreach ($invoices as $invoice) {
                    // Line totals must equal the stored header totals.
                    $this->expect($invoice, 'lines sum', (string) $invoice->total, Money::add((string) ($invoice->lines_total ?? 0), '0.00'));

                    // Posted carries its value on the books; void nets to zero.
                    $expected = $invoice->status === Invoice::STATUS_POSTED ? (string) $invoice->total : '0.00';
                    $this->expect($invoice, 'ledger net', $expected, $this->net($invoice));
                }
            });
    }

    private function checkCreditNotes(): void
    {
        CreditNote::query()->where('status', 'posted')->chunkById(200, function ($notes) {
            foreach ($notes as $note) {
                $this->expect($note, 'ledger net', Money::subtract('0.00', (string) $note->total), $this->net($note));
            }
        });
    }

    private function checkReceipts(): void
    {
        Receipt::query()->chunkById(200, function ($receipts) {
            foreach ($receipts as $receipt) {
                // Customer credit and cash debit always cancel out.
                $this->expect($receipt, 'ledger net', '0.00', $this->net($receipt));

                $allocated = (string) $receipt->allocations()->sum('amount');
                if ($receipt->status === 'posted' && Money::compare($allocated, (string) $receipt->amount) > 0) {
                    $this->problems++;
                    $this->warn("Receipt #{$receipt->id}: allocations {$allocated} exceed amount {$receipt->amount}");
                }
            }
        });
    }

    private function checkExpenses(): void
    {
        Expense::query()->chunkById(200, function ($expenses) {
            foreach ($expenses as $expense) {
                $expected = match ($expense->status) {
                    'approved' => Money::subtract('0.00', (string) $expense->amount),
                    default => '0.00', // pending has no entries; void nets out
                };
                $this->expect($expense, 'ledger net', $expected, $this->net($expense));
            }
        });
    }

    private function checkAdvances(): void
    {
        Advance::query()->whereIn('status', ['active', 'settled'])->chunkById(200, function ($advances) {
            foreach ($advances as $advance) {
                // Employee debit + cash credit cancel; both legs must exist.
                $this->expect($advance, 'ledger net', '0.00', $this->net($advance));

                $debits = (string) LedgerEntry::where('source_type', $advance->getMorphClass())
                    ->where('source_id', $advance->id)->sum('debit');
                $this->expect($advance, 'debit leg', (string) $advance->amount, Money::add($debits, '0.00'));
            }
        });
    }

    private function checkCharges(): void
    {
        EmployeeCharge::query()->whereIn('status', ['approved', 'settled'])->chunkById(200, function ($charges) {
            foreach ($charges as $charge) {
                // Shortage moves custody -> employee (net 0); fines/damages only debit.
                $expected = $charge->type === 'shortage' ? '0.00' : (string) $charge->amount;
                $this->expect($charge, 'ledger net', $expected, $this->net($charge));
            }
        });
    }

    private function checkSimple(string $class, string $amountColumn): void
    {
        $class::query()->chunkById(200, function ($docs) use ($amountColumn) {
            foreach ($docs as $doc) {
                $this->expect($doc, 'ledger net', '0.00', $this->net($doc));

                $debits = (string) LedgerEntry::where('source_type', $doc->getMorphClass())
                    ->where('source_id', $doc->getKey())->sum('debit');
                $this->expect($doc, 'debit leg', (string) $doc->{$amountColumn}, Money::add($debits, '0.00'));
            }
        });
    }

    /** A paid payroll line must credit the employee by exactly its recoveries. */
    private function checkPayrollRecoveries(): void
    {
        PayrollLine::query()->whereNotNull('paid_at')->chunkById(200, function ($lines) {
            foreach ($lines as $line) {
                $expected = Money::add((string) $line->advance_recovery, (string) $line->charges_recovery);

                $credits = (string) LedgerEntry::where('source_type', $line->getMorphClass())
                    ->where('source_id', $line->id)
                    ->sum('credit');

                $this->expect($line, 'recovery credit', $expected, Money::add($credits, '0.00'));
            }
        });
    }

    /** Entries whose source row no longer exists (opening balances excepted). */
    private function checkOrphans(): void
    {
        LedgerEntry::query()
            ->whereNotNull('source_type')
            ->chunkById(500, function ($entries) {
                foreach ($entries->groupBy('source_type') as $type => $group) {
                    if (! class_exists($type)) {
                        $this->problems++;
                        $this->warn("Ledger: unknown source type {$type} ({$group->count()} rows)");

                        continue;
                    }

                    $existing = $type::query()->whereIn((new $type)->getKeyName(), $group->pluck('source_id'))->pluck('id')->flip();

                    foreach ($group as $entry) {
                        if (! isset($existing[$entry->source_id])) {
                            $this->problems++;
                            $this->warn("Ledger entry #{$entry->id}: source {$type}#{$entry->source_id} is missing");
                        }
                    }
                }
            });
    }
}

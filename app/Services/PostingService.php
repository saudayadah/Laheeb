<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single gate every document posts through. All methods MUST be called
 * inside a DB transaction together with the document state change.
 */
class PostingService
{
    public function postInvoice(Invoice $invoice): void
    {
        $this->assertInTransaction();

        [$accountType, $accountId] = $this->cashDestination($invoice);

        $this->entry(
            $accountType,
            $accountId,
            $invoice->invoice_date,
            debit: (string) $invoice->total,
            credit: '0.00',
            source: $invoice,
            description: __('ledger.invoice', ['number' => $invoice->displayNumber()]),
            userId: $invoice->posted_by,
        );
    }

    public function reverseInvoice(Invoice $invoice, string $reason): void
    {
        $this->assertInTransaction();

        foreach ($invoice->ledgerEntries()->get() as $entry) {
            $this->entry(
                $entry->account_type,
                (int) $entry->account_id,
                today(),
                debit: (string) $entry->credit,
                credit: (string) $entry->debit,
                source: $invoice,
                description: __('ledger.void', ['number' => $invoice->displayNumber(), 'reason' => $reason]),
                userId: $invoice->voided_by,
            );
        }
    }

    public function postCreditNote(CreditNote $note): void
    {
        $this->assertInTransaction();

        $invoice = $note->invoice;

        // Mirrors cashDestination exactly: mada resolves BEFORE driver, so a
        // return against a mada van sale credits the bank it was paid into.
        if (($invoice?->payment_method ?? 'credit') === 'credit' && $note->customer_id !== null) {
            $type = LedgerEntry::CUSTOMER;
            $id = $note->customer_id;
        } elseif (($invoice?->payment_method ?? null) === 'mada') {
            $type = LedgerEntry::BANK;
            $id = LedgerEntry::MAIN_BANK_ID;
        } elseif ($invoice?->driver_id !== null) {
            $type = LedgerEntry::DRIVER;
            $id = $invoice->driver_id;
        } else {
            $type = LedgerEntry::CASH_BOX;
            $id = LedgerEntry::MAIN_CASH_BOX_ID;
        }

        $this->entry(
            $type,
            $id,
            $note->note_date,
            debit: '0.00',
            credit: (string) $note->total,
            source: $note,
            description: __('ledger.credit_note', ['number' => $note->displayNumber()]),
            userId: $note->created_by,
        );
    }

    /**
     * Cash from a reclassified invoice moves between accounts.
     * Posts the reversal of the old destination and the debit of the new one.
     */
    public function repostInvoice(Invoice $invoice, string $reason): void
    {
        $this->assertInTransaction();

        // Net out whatever the invoice currently has on the books...
        $existing = $invoice->ledgerEntries()->get()->groupBy(fn ($e) => $e->account_type.':'.$e->account_id);

        foreach ($existing as $key => $entries) {
            $debits = Money::sum($entries->pluck('debit'));
            $credits = Money::sum($entries->pluck('credit'));
            $net = Money::subtract($debits, $credits);

            if (! Money::isZero($net)) {
                [$type, $id] = explode(':', $key);
                $this->entry(
                    $type,
                    (int) $id,
                    today(),
                    debit: '0.00',
                    credit: $net,
                    source: $invoice,
                    description: __('ledger.reclass', ['number' => $invoice->displayNumber(), 'reason' => $reason]),
                    userId: $invoice->reclassified_by,
                );
            }
        }

        // ...then post it fresh against the new destination.
        [$accountType, $accountId] = $this->cashDestination($invoice);

        $this->entry(
            $accountType,
            $accountId,
            today(),
            debit: (string) $invoice->total,
            credit: '0.00',
            source: $invoice,
            description: __('ledger.reclass', ['number' => $invoice->displayNumber(), 'reason' => $reason]),
            userId: $invoice->reclassified_by,
        );
    }

    /**
     * Where the value of an invoice lands when posted.
     *
     * @return array{0: string, 1: int}
     */
    private function cashDestination(Invoice $invoice): array
    {
        return match (true) {
            $invoice->payment_method === 'credit' && $invoice->customer_id !== null => [LedgerEntry::CUSTOMER, $invoice->customer_id],
            $invoice->payment_method === 'mada' => [LedgerEntry::BANK, LedgerEntry::MAIN_BANK_ID],
            $invoice->driver_id !== null => [LedgerEntry::DRIVER, $invoice->driver_id],
            default => [LedgerEntry::CASH_BOX, LedgerEntry::MAIN_CASH_BOX_ID],
        };
    }

    public function entry(
        string $accountType,
        int $accountId,
        mixed $date,
        string $debit,
        string $credit,
        ?Model $source,
        ?string $description,
        ?int $userId,
    ): LedgerEntry {
        $this->assertInTransaction();

        return LedgerEntry::create([
            'account_type' => $accountType,
            'account_id' => $accountId,
            'entry_date' => $date,
            'debit' => $debit,
            'credit' => $credit,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'description' => $description,
            'created_by' => $userId,
        ]);
    }

    private function assertInTransaction(): void
    {
        if (! DB::transactionLevel()) {
            throw new \LogicException('PostingService must be used inside a DB transaction.');
        }
    }
}

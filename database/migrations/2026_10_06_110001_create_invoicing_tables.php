<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gapless per-series numbering, allocated under a row lock inside the
        // posting transaction.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series', 10)->unique();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        // One immutable row per money movement, per sub-ledger account.
        // All balances everywhere are derived from this table.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('account_type', 20); // customer | driver | cash_box | bank | supplier | employee
            $table->unsignedBigInteger('account_id');
            $table->date('entry_date');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_type', 'account_id', 'entry_date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('series', 10)->default('INV');
            $table->unsignedBigInteger('number')->nullable(); // allocated at posting
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_date');
            $table->string('type', 15)->default('simplified'); // tax | simplified
            $table->string('source', 15)->default('delivery'); // delivery | van | counter | daily_retail
            $table->string('payment_method', 10)->default('cash'); // cash | mada | credit
            $table->string('status', 10)->default('draft'); // draft | posted | void
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->boolean('prices_include_vat')->default(true);
            $table->string('hash', 64)->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->text('qr_payload')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->string('reclass_reason', 255)->nullable();
            $table->timestamp('reclassified_at')->nullable();
            $table->foreignId('reclassified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['series', 'number']);
            $table->index(['invoice_date', 'status']);
            $table->index(['customer_id', 'invoice_date']);
            $table->index(['driver_id', 'invoice_date']);
            $table->index(['payment_method', 'invoice_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 255)->nullable(); // for product-less lines (daily retail total)
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 10, 4);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2); // excluding VAT
            $table->decimal('line_vat', 14, 2);
            $table->decimal('line_total', 14, 2);    // including VAT
            $table->timestamps();

            $table->index('invoice_id');
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('series', 10)->default('CRN');
            $table->unsignedBigInteger('number')->nullable();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('note_date');
            $table->string('reason', 255);
            $table->string('status', 10)->default('posted'); // posted | void
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series', 'number']);
            $table->index(['note_date', 'status']);
            $table->index(['customer_id', 'note_date']);
        });

        Schema::create('credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('condition', 10)->default('good'); // good | damaged
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 10, 4);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2);
            $table->decimal('line_vat', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_lines');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('document_sequences');
    }
};

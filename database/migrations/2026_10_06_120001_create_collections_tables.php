<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('series', 10)->default('RCT');
            $table->unsignedBigInteger('number')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('receipt_date');
            $table->decimal('amount', 14, 2);
            $table->string('method', 10)->default('cash'); // cash | mada | transfer
            $table->string('reference', 100)->nullable();
            // Who physically received the money (driver on route, or office).
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('posted'); // posted | void
            $table->string('void_reason', 255)->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series', 'number']);
            $table->index(['receipt_date', 'status']);
            $table->index(['customer_id', 'receipt_date']);
            $table->index(['received_by', 'receipt_date']);
        });

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index('invoice_id');
        });

        // Driver hands collected cash to the office.
        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->date('handover_date');
            $table->decimal('amount', 14, 2);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('daily_close_id')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('daily_closes', function (Blueprint $table) {
            $table->id();
            $table->date('close_date');
            $table->string('closeable_type', 10); // driver | counter
            // driver: the user id. counter: 0.
            $table->unsignedBigInteger('closeable_id')->default(0);
            $table->decimal('expected', 14, 2)->default(0);
            $table->decimal('counted', 14, 2)->default(0);
            $table->decimal('variance', 14, 2)->default(0);
            $table->string('status', 10)->default('pending'); // pending | approved
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['close_date', 'closeable_type', 'closeable_id']);
        });

        Schema::create('daily_close_denominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_close_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('denomination'); // 500 200 100 50 20 10 5 1
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['daily_close_id', 'denomination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_close_denominations');
        Schema::dropIfExists('daily_closes');
        Schema::dropIfExists('cash_handovers');
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('receipts');
    }
};

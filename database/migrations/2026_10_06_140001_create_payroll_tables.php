<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            $table->string('nationality', 50)->nullable();
            // Encrypted at rest; only Owner and Accountant may see it.
            $table->text('iqama_number')->nullable();
            $table->date('iqama_expiry')->nullable();
            $table->string('job', 15)->default('worker'); // baker | driver | worker | other
            $table->decimal('basic_salary', 10, 2)->default(0);
            $table->date('join_date')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('advance_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('recovered_total', 10, 2)->default(0);
            $table->string('paid_from', 15)->default('counter_cash'); // counter_cash | bank
            $table->string('plan', 15)->default('full'); // full | installment
            $table->decimal('installment_amount', 10, 2)->nullable();
            $table->string('status', 10)->default('active'); // pending | active | settled
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        // Fines, damages and transferred driver cash shortages.
        Schema::create('employee_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('charge_date');
            $table->string('type', 10); // fine | damage | shortage
            $table->decimal('amount', 10, 2);
            $table->decimal('recovered_total', 10, 2)->default(0);
            $table->string('status', 10)->default('pending'); // pending | approved | settled
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique(); // YYYY-MM
            $table->string('status', 10)->default('draft'); // draft | reviewed | approved | paid
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('basic', 10, 2)->default(0);
            $table->decimal('overtime', 10, 2)->default(0);
            $table->decimal('leave_allowance', 10, 2)->default(0);
            $table->decimal('additions', 10, 2)->default(0);
            $table->decimal('absence_days', 5, 2)->default(0);
            $table->decimal('absence_amount', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('advance_recovery', 10, 2)->default(0);
            $table->decimal('charges_recovery', 10, 2)->default(0);
            $table->decimal('net', 10, 2)->default(0);
            $table->string('payment_method', 10)->nullable(); // cash | bank
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_charges');
        Schema::dropIfExists('advances');
        Schema::dropIfExists('employees');
    }
};

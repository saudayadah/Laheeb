<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flour by type, foam boxes, printed bags... stock in simple units.
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('unit', 30)->default('كيس'); // bag / carton / unit
            $table->decimal('reorder_level', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('raw_material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
            $table->date('movement_date');
            $table->string('direction', 3); // in | out
            $table->decimal('qty', 10, 2);
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['raw_material_id', 'movement_date']);
        });

        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('service_date');
            $table->string('task', 150);
            $table->unsignedInteger('odometer')->nullable();
            $table->date('next_due_date')->nullable();
            $table->unsignedInteger('next_due_odometer')->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'service_date']);
        });

        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('expected_return');
            $table->date('actual_return')->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');
        Schema::dropIfExists('vehicle_maintenances');
        Schema::dropIfExists('raw_material_movements');
        Schema::dropIfExists('raw_materials');
    }
};

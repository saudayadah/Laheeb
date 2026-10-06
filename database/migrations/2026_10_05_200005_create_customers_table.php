<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('name_en', 150)->nullable();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 15)->default('wholesale');
            $table->string('payment_term', 10)->default('cash');
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->unsignedSmallInteger('credit_days')->nullable();
            $table->foreignId('delivery_route_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('stop_sequence')->default(0);
            $table->string('city', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('map_url', 500)->nullable();
            $table->string('vat_number', 20)->nullable();
            $table->string('cr_number', 20)->nullable();
            $table->string('national_address', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['delivery_route_id', 'stop_sequence']);
            $table->index('name');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

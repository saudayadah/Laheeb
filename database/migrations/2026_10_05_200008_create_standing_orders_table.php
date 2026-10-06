<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Carbon convention: 0 = Sunday .. 6 = Saturday.
            $table->unsignedTinyInteger('weekday');
            $table->unsignedInteger('qty');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['customer_id', 'product_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_orders');
    }
};

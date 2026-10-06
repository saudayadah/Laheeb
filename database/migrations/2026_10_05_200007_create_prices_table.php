<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per agreed price. Scope is defined by which foreign keys are set:
        //   customer + product  -> price for that product for that customer
        //   customer only       -> customer default price (all products)
        //   group + product     -> price for that product for all group branches
        //   group only          -> group default price
        //   product only        -> dated product price (fallback before products.default_price)
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 4);
            $table->date('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'product_id', 'effective_from']);
            $table->index(['customer_group_id', 'product_id', 'effective_from']);
            $table->index(['product_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};

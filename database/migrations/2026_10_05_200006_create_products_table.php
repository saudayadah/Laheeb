<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->string('category', 20)->index();
            $table->unsignedSmallInteger('size_cm')->nullable();
            $table->string('unit', 20)->default('loaf');
            $table->decimal('default_price', 10, 4)->default(0);
            // Null means: use the global VAT rate from settings.
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

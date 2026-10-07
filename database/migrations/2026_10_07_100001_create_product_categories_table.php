<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Open the product catalogue: categories become user-managed rows
     * instead of a fixed bread-only list. Existing string categories are
     * migrated into the new table and re-linked.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 100)->unique();
            $table->string('name_en', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_category_id')
                ->nullable()
                ->after('name_en')
                ->constrained()
                ->nullOnDelete();
        });

        // Backfill: one category row per legacy slug, in display order.
        $legacy = [
            'saj' => ['صاج', 'Saj'],
            'milk' => ['حليب', 'Milk'],
            'arabic' => ['عربي', 'Arabic'],
            'wheat' => ['بر', 'Whole-wheat'],
            'red' => ['خبز أحمر', 'Red bread'],
        ];

        $sort = 0;
        foreach ($legacy as $slug => [$ar, $en]) {
            if (! DB::table('products')->where('category', $slug)->exists()) {
                continue;
            }

            $id = DB::table('product_categories')->insertGetId([
                'name_ar' => $ar,
                'name_en' => $en,
                'sort_order' => ++$sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')->where('category', $slug)->update(['product_category_id' => $id]);
        }

        // Any category slug outside the legacy list becomes its own row too.
        $others = DB::table('products')
            ->whereNull('product_category_id')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        foreach ($others as $slug) {
            $id = DB::table('product_categories')->insertGetId([
                'name_ar' => $slug,
                'sort_order' => ++$sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')->where('category', $slug)->update(['product_category_id' => $id]);
        }

        // The old column carries a plain index — drop it before the column.
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['product_category_id', 'name_ar']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['product_category_id', 'name_ar']);
            $table->string('category', 20)->nullable()->after('name_en')->index();
        });

        foreach (DB::table('product_categories')->get() as $category) {
            DB::table('products')
                ->where('product_category_id', $category->id)
                ->update(['category' => $category->name_ar]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_category_id');
        });

        Schema::dropIfExists('product_categories');
    }
};

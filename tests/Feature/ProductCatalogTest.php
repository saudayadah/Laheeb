<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Imports\ProductsImporter;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
});

test('the owner can create categories and products inside them', function () {
    $this->actingAs($this->owner)
        ->post(route('products.categories.store'), ['name_ar' => 'معجنات', 'name_en' => 'Pastries'])
        ->assertRedirect();

    $category = ProductCategory::where('name_ar', 'معجنات')->firstOrFail();

    $this->actingAs($this->owner)->post(route('products.store'), [
        'name_ar' => 'معمول تمر',
        'product_category_id' => $category->id,
        'unit' => 'piece',
        'default_price' => '1.50',
    ])->assertRedirect();

    expect(Product::where('name_ar', 'معمول تمر')->first()->category->name_ar)->toBe('معجنات');
});

test('renaming a category keeps its products attached', function () {
    $category = ProductCategory::create(['name_ar' => 'حلويات']);
    $product = Product::factory()->create(['product_category_id' => $category->id]);

    $this->actingAs($this->owner)
        ->patch(route('products.categories.update', $category), ['name_ar' => 'حلويات شرقية', 'active' => true])
        ->assertRedirect();

    expect($product->refresh()->category->name_ar)->toBe('حلويات شرقية');
});

test('the products importer creates unknown categories on the fly', function () {
    $importer = app(ProductsImporter::class);

    $rows = collect([
        collect(['name_ar' => 'كرواسون', 'name_en' => '', 'category' => 'معجنات', 'size_cm' => '', 'default_price' => '2', 'vat_rate' => '', 'sort_order' => '']),
        collect(['name_ar' => 'صاج 60', 'name_en' => '', 'category' => 'saj', 'size_cm' => '60', 'default_price' => '0.5', 'vat_rate' => '', 'sort_order' => '']),
    ]);

    $validated = $importer->validateRows($rows, $this->owner);
    expect(collect($validated)->every(fn ($r) => $r['errors'] === []))->toBeTrue();

    $importer->commit($validated, $this->owner);

    expect(ProductCategory::where('name_ar', 'معجنات')->exists())->toBeTrue()
        ->and(Product::where('name_ar', 'كرواسون')->first()->category->name_ar)->toBe('معجنات')
        ->and(Product::where('name_ar', 'صاج 60')->first()->category->name_ar)->toBe('صاج');
});

test('a duplicate category name is rejected', function () {
    ProductCategory::create(['name_ar' => 'مخبوزات']);

    $this->actingAs($this->owner)
        ->post(route('products.categories.store'), ['name_ar' => 'مخبوزات'])
        ->assertSessionHasErrors('name_ar');
});

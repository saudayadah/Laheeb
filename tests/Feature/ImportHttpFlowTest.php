<?php

use App\Exports\TemplateExport;
use App\Models\Customer;
use App\Models\User;
use App\Services\Imports\CustomersImporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
});

test('the imports screen and template download work', function () {
    $this->actingAs($this->owner)->get('/imports')->assertOk();

    $this->actingAs($this->owner)
        ->get('/imports/customers/template')
        ->assertOk()
        ->assertDownload('laheeb-customers-template.xlsx');
});

test('the full preview then commit flow imports the valid rows', function () {
    $importer = new CustomersImporter;

    // Build a real xlsx from the template definition (2 valid example rows).
    Excel::store(new TemplateExport($importer->headings(), $importer->exampleRows()), 'test-import.xlsx', 'local');
    $file = new UploadedFile(
        storage_path('app/private/test-import.xlsx'),
        'customers.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $response = $this->actingAs($this->owner)->post('/imports/customers/preview', ['file' => $file]);

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('imports/preview')
        ->where('validCount', 2)
        ->where('errorCount', 0)
    );

    $token = $response->viewData('page')['props']['token'];

    $this->actingAs($this->owner)
        ->post('/imports/customers/commit', ['token' => $token])
        ->assertRedirect('/imports');

    expect(Customer::count())->toBe(2);
    expect(Customer::where('code', '101')->exists())->toBeTrue();
});

test('a clerk cannot run imports', function () {
    $clerk = User::factory()->create();
    $clerk->assignRole('clerk');

    $this->actingAs($clerk)->get('/imports')->assertForbidden();
});

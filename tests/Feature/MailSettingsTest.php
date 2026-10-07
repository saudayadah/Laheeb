<?php

use App\Models\User;
use App\Notifications\ResetPasswordArabic;
use App\Support\AppSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('owner');
});

test('the owner saves mail settings and the password is stored encrypted', function () {
    $this->actingAs($this->owner)->patch(route('settings.mail.update'), [
        'mail_host' => 'mail.laheeb.sa',
        'mail_port' => 465,
        'mail_username' => 'no-reply@laheeb.sa',
        'mail_from_name' => 'لهيب',
        'mail_password' => 'secret-123',
    ])->assertRedirect();

    $stored = (string) AppSettings::get('mail_password');

    expect($stored)->not->toBe('secret-123')
        ->and(Crypt::decryptString($stored))->toBe('secret-123')
        ->and(AppSettings::get('mail_host'))->toBe('mail.laheeb.sa');
});

test('leaving the password empty keeps the stored one', function () {
    AppSettings::set('mail_password', Crypt::encryptString('old-pass'));

    $this->actingAs($this->owner)->patch(route('settings.mail.update'), [
        'mail_host' => 'mail.laheeb.sa',
        'mail_port' => 465,
        'mail_username' => 'no-reply@laheeb.sa',
        'mail_password' => '',
    ])->assertRedirect();

    expect(Crypt::decryptString((string) AppSettings::get('mail_password')))->toBe('old-pass');
});

test('an accountant cannot open or change mail settings', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');

    $this->actingAs($accountant)->get(route('settings.mail.edit'))->assertForbidden();
    $this->actingAs($accountant)->patch(route('settings.mail.update'))->assertForbidden();
});

test('the test button reports missing configuration gracefully', function () {
    $this->actingAs($this->owner)
        ->post(route('settings.mail.test'))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('forgot password sends the Arabic reset notification', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => $this->owner->email])->assertRedirect();

    Notification::assertSentTo($this->owner, ResetPasswordArabic::class);
});

test('the app version is shared with every page', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->has('appVersion'));
});

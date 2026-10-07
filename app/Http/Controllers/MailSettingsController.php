<?php

namespace App\Http\Controllers;

use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class MailSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $all = AppSettings::all();

        return Inertia::render('settings/mail', [
            'settings' => [
                'mail_host' => (string) $all['mail_host'],
                'mail_port' => (string) $all['mail_port'],
                'mail_username' => (string) $all['mail_username'],
                'mail_from_name' => (string) $all['mail_from_name'],
                'has_password' => $all['mail_password'] !== '',
            ],
            'testRecipient' => $request->user()->email,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate([
            'mail_host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['required', 'email', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:100'],
            'mail_password' => ['nullable', 'string', 'max:190'],
        ]);

        $values = [
            'mail_host' => trim($data['mail_host']),
            'mail_port' => (string) $data['mail_port'],
            'mail_username' => trim($data['mail_username']),
            'mail_from_name' => trim($data['mail_from_name'] ?? ''),
        ];

        // An empty password field means "keep the saved one".
        if (($data['mail_password'] ?? '') !== '') {
            $values['mail_password'] = Crypt::encryptString($data['mail_password']);
        }

        AppSettings::setMany($values);

        activity()
            ->causedBy($request->user())
            ->withProperties(['keys' => array_keys($values)]) // never log the password itself
            ->log('settings.mail_updated');

        return back()->with('success', __('common.saved'));
    }

    public function test(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        if ((string) AppSettings::get('mail_host') === '') {
            return back()->with('error', __('settings.mail_not_configured'));
        }

        $to = $request->user()->email;

        try {
            Mail::raw(__('settings.mail_test_body'), function ($message) use ($to) {
                $message->to($to)->subject(__('settings.mail_test_subject'));
            });
        } catch (\Throwable $e) {
            return back()->with('error', __('settings.mail_test_failed').': '.mb_substr($e->getMessage(), 0, 200));
        }

        return back()->with('success', __('settings.mail_test_ok', ['email' => $to]));
    }
}

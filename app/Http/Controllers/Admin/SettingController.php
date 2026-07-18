<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Models\Share;
use App\Services\AdminAudit;
use App\Services\SiteSettings;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Uri;
use Illuminate\View\View;

class SettingController extends Controller
{
    private const SETTING_KEYS = [
        'site_name',
        'site_url',
        'timezone',
        'session_lifetime_minutes',
        'registration_enabled',
        'sharing_enabled',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_scheme',
        'mail_username',
        'mail_from_address',
        'mail_from_name',
    ];

    public function edit(Request $request, SiteSettings $settings): View
    {
        $values = $settings->getMany(self::SETTING_KEYS);
        $mailPasswordStatus = $settings->secretStatus('mail_password');

        return view('admin.settings.edit', [
            'settings' => $values,
            'siteUrl' => $values['site_url'] ?: rtrim($request->root(), '/'),
            'mailPasswordConfigured' => $mailPasswordStatus === 'valid',
            'mailPasswordInvalid' => $mailPasswordStatus === 'invalid',
            'settingsRevision' => $settings->revision(),
        ]);
    }

    public function update(
        UpdateSiteSettingsRequest $request,
        SiteSettings $settings,
        AdminAudit $audit,
        UserAccessService $access,
    ): RedirectResponse {
        $validated = $request->validated();
        $values = [
            'site_name' => trim($validated['site_name']),
            'site_url' => rtrim((string) Uri::of($validated['site_url']), '/'),
            'timezone' => $validated['timezone'],
            'session_lifetime_minutes' => (int) $validated['session_lifetime_minutes'],
            'registration_enabled' => $request->boolean('registration_enabled'),
            'sharing_enabled' => $request->boolean('sharing_enabled'),
            'mail_mailer' => $validated['mail_mailer'],
            'mail_host' => trim((string) ($validated['mail_host'] ?? '')),
            'mail_port' => (int) ($validated['mail_port'] ?? 587),
            'mail_scheme' => filled($validated['mail_scheme'] ?? null) ? $validated['mail_scheme'] : null,
            'mail_username' => filled($validated['mail_username'] ?? null) ? trim($validated['mail_username']) : null,
            'mail_from_address' => trim($validated['mail_from_address']),
            'mail_from_name' => trim($validated['mail_from_name']),
        ];

        if ($request->boolean('clear_mail_password')) {
            $values['mail_password'] = null;
        } elseif (filled($validated['mail_password'] ?? null)) {
            $values['mail_password'] = $validated['mail_password'];
        }

        $conflict = false;

        Cache::lock('admin:site-settings:update', 15)->block(5, function () use ($request, $settings, $audit, $access, $values, &$conflict): void {
            DB::transaction(function () use ($request, $settings, $audit, $access, $values, &$conflict): void {
                $access->lockAdminActor($request->user());

                if (! hash_equals($request->string('settings_revision')->toString(), $settings->revision())) {
                    $conflict = true;

                    return;
                }

                $beforeValues = $settings->getMany(self::SETTING_KEYS);
                $beforePasswordConfigured = $settings->hasStoredSecret('mail_password');
                $before = $this->auditSnapshot($beforeValues, $beforePasswordConfigured, false);

                $settings->setMany($values);

                if ($beforeValues['sharing_enabled'] && ! $values['sharing_enabled']) {
                    Share::query()
                        ->whereNull('revoked_at')
                        ->update([
                            'access_version' => DB::raw('access_version + 1'),
                            'updated_at' => now(),
                        ]);
                }

                $afterValues = array_replace($beforeValues, $values);
                $afterPasswordConfigured = array_key_exists('mail_password', $values)
                    ? filled($values['mail_password'])
                    : $beforePasswordConfigured;
                $after = $this->auditSnapshot(
                    $afterValues,
                    $afterPasswordConfigured,
                    array_key_exists('mail_password', $values),
                );

                $audit->record($request, 'site.settings_updated', null, $before, $after);
            }, 3);
        });

        if ($conflict) {
            return back()->withErrors(['settings' => '页面配置已被其他管理员更新，已载入最新配置，请确认后重试。']);
        }

        return back()->with('status', '系统设置已更新。');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function auditSnapshot(
        array $values,
        bool $mailPasswordConfigured,
        bool $mailPasswordChanged,
    ): array {
        $mailUsernameConfigured = filled($values['mail_username'] ?? null);
        unset($values['mail_username']);

        $values['mail_username_configured'] = $mailUsernameConfigured;
        $values['mail_password_configured'] = $mailPasswordConfigured;
        $values['mail_password_changed'] = $mailPasswordChanged;
        $values['mail_password_cleared'] = $mailPasswordChanged && ! $mailPasswordConfigured;

        return $values;
    }
}

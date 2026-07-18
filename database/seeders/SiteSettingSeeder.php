<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site_name' => [
                'value' => (string) config('kexi.settings_defaults.site_name'),
                'type' => 'string',
            ],
            'site_url' => [
                'value' => config('kexi.settings_defaults.site_url'),
                'type' => 'nullable_string',
            ],
            'timezone' => [
                'value' => (string) config('kexi.settings_defaults.timezone'),
                'type' => 'string',
            ],
            'session_lifetime_minutes' => [
                'value' => (string) config('kexi.settings_defaults.session_lifetime_minutes'),
                'type' => 'integer',
            ],
            'registration_enabled' => [
                'value' => config('kexi.settings_defaults.registration_enabled') ? 'true' : 'false',
                'type' => 'boolean',
            ],
            'sharing_enabled' => [
                'value' => config('kexi.settings_defaults.sharing_enabled') ? 'true' : 'false',
                'type' => 'boolean',
            ],
            'mail_mailer' => [
                'value' => (string) config('kexi.settings_defaults.mail_mailer'),
                'type' => 'string',
            ],
            'mail_host' => [
                'value' => (string) config('kexi.settings_defaults.mail_host'),
                'type' => 'string',
            ],
            'mail_port' => [
                'value' => (string) config('kexi.settings_defaults.mail_port'),
                'type' => 'integer',
            ],
            'mail_scheme' => [
                'value' => config('kexi.settings_defaults.mail_scheme'),
                'type' => 'nullable_string',
            ],
            'mail_username' => [
                'value' => config('kexi.settings_defaults.mail_username'),
                'type' => 'nullable_string',
            ],
            'mail_password' => [
                'value' => null,
                'type' => 'secret',
            ],
            'mail_from_address' => [
                'value' => (string) config('kexi.settings_defaults.mail_from_address'),
                'type' => 'string',
            ],
            'mail_from_name' => [
                'value' => (string) config('kexi.settings_defaults.mail_from_name'),
                'type' => 'string',
            ],
        ];

        foreach ($defaults as $key => $attributes) {
            SiteSetting::query()->firstOrCreate(['key' => $key], $attributes);
        }
    }
}

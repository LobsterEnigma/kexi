<?php

use App\Services\SiteSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kexi:auth-disable {feature=all : all, turnstile or passkeys}', function () {
    $feature = $this->argument('feature');
    if (! in_array($feature, ['all', 'turnstile', 'passkeys'], true)) {
        $this->error('Use all, turnstile or passkeys.');

        return 1;
    }
    $values = [];
    if ($feature !== 'passkeys') {
        $values['turnstile_enabled'] = false;
    }
    if ($feature !== 'turnstile') {
        $values['passkeys_enabled'] = false;
    }
    app(SiteSettings::class)->setMany($values);
    if (Schema::hasTable('passkey_challenges')) {
        DB::table('passkey_challenges')->delete();
    }
    $this->info('已关闭指定登录功能；密码登录仍可用，现有密钥和配置未删除。');
})->purpose('Recover password login by disabling optional login security features');

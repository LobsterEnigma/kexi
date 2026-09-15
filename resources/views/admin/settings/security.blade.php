<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-semibold text-slate-500">管理控制台</p><h1 class="text-xl font-semibold text-slate-900">登录安全</h1></div></x-slot>
    @include('admin.partials.navigation')
    <main class="auth-settings mx-auto max-w-5xl px-4 py-6 sm:px-6">
        @include('admin.partials.feedback')
        <div class="auth-settings__intro"><i data-lucide="shield-check"></i><div><h2>更安心的登录方式</h2><p>人机验证和通行密钥可分别开启，所有配置在这里管理。</p></div></div>
        <p class="auth-settings__site">当前站点：<strong>{{ $siteUrl ?: '尚未设置' }}</strong><a href="{{ route('admin.settings.edit') }}">修改站点网址</a></p>
        <form method="POST" action="{{ route('admin.security.update') }}" x-data="{ turnstile: @js((bool)old('turnstile_enabled',$settings['turnstile_enabled'])) }">
            @csrf @method('PUT')<input type="hidden" name="revision" value="{{ $revision }}">
            <section class="auth-settings__card">
                <label class="auth-settings__toggle"><span class="auth-settings__icon"><i data-lucide="shield-check"></i></span><span><strong>Turnstile 人机验证</strong><small>减少自动化注册和恶意登录尝试</small></span><input type="hidden" name="turnstile_enabled" value="0"><input type="checkbox" name="turnstile_enabled" value="1" x-model="turnstile"></label>
                <div class="auth-settings__body">
                    <p class="auth-settings__help">在 <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">Cloudflare Turnstile</a> 创建托管式（Managed）组件，将上方站点域名加入允许列表，再填入以下密钥。</p>
                    <div class="auth-settings__grid">
                        <label class="wb-field-group"><span class="wb-label">Site Key（公开密钥）</span><input class="wb-field" name="turnstile_site_key" maxlength="255" value="{{ old('turnstile_site_key',$settings['turnstile_site_key']) }}" autocomplete="off" placeholder="粘贴 Site Key"></label>
                        <label class="wb-field-group"><span class="wb-label">Secret Key（私密密钥） <span class="auth-settings__badge">{{ ['valid'=>'已加密保存','missing'=>'未配置','invalid'=>'需重新配置'][$secretStatus] }}</span></span><input class="wb-field" type="password" name="turnstile_secret" maxlength="2048" autocomplete="new-password" placeholder="{{ $secretStatus==='valid'?'留空保留现有密钥':'粘贴 Secret Key' }}"><span class="auth-settings__help">密钥只保存在服务器，保存后不再回显。</span></label>
                    </div>
                    <div class="auth-settings__scopes"><span>应用于</span>@foreach(['login'=>'登录（密码和通行密钥）','register'=>'注册'] as $key=>$label)<label><input type="hidden" name="turnstile_{{ $key }}" value="0"><input type="checkbox" name="turnstile_{{ $key }}" value="1" @checked(old('turnstile_'.$key,$settings['turnstile_'.$key]))>{{ $label }}</label>@endforeach</div>
                    <input type="hidden" name="clear_turnstile_secret" value="0">
                    @if($secretStatus!=='missing')<label class="auth-settings__clear"><input type="checkbox" name="clear_turnstile_secret" value="1">清除已保存的 Secret Key（需先关闭验证）</label>@endif
                </div>
            </section>
            <section class="auth-settings__card">
                <label class="auth-settings__toggle"><span class="auth-settings__icon"><i data-lucide="fingerprint"></i></span><span><strong>Passkey 通行密钥</strong><small>使用指纹、面容、设备 PIN 或安全密钥登录</small></span><input type="hidden" name="passkeys_enabled" value="0"><input type="checkbox" name="passkeys_enabled" value="1" @checked(old('passkeys_enabled',$settings['passkeys_enabled']))></label>
                <div class="auth-settings__body"><label class="wb-field-group"><span class="wb-label">设备上显示的站点名称</span><input class="wb-field" name="passkeys_name" maxlength="80" value="{{ old('passkeys_name',$settings['passkeys_name']) }}" placeholder="留空使用站点名称"></label><p class="auth-settings__help mt-3">开启后，用户在「账户设置 → 通行密钥」添加自己的密钥。无需 API Key，密码登录继续可用。域名自动使用上方站点网址，正式环境需要 HTTPS；更换域名后需要在新域名重新添加密钥。关闭功能不会删除已有密钥。</p></div>
            </section>
            <div class="auth-settings__footer"><span>新设置保存后生效</span><button class="wb-btn wb-btn--primary" type="submit"><i data-lucide="check"></i>保存登录安全设置</button></div>
        </form>
    </main>
</x-app-layout>

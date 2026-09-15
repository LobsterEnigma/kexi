@php
    $passkeysEnabled = app(\App\Services\SiteSettings::class)->bool('passkeys_enabled');
    $passkeys = $user->passkeys()->latest()->get();
@endphp
<header><h2 class="text-lg font-semibold text-slate-900">通行密钥</h2><p class="mt-1 text-sm leading-6 text-slate-600">使用指纹、面容或设备 PIN 登录。网站只保存公钥，不会取得你的指纹或面容数据。</p></header>
@if(session('passkey_status') || request()->boolean('passkey_added'))<p class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('passkey_status') ?: '通行密钥已添加。' }}</p>@endif
@if($errors->any())<div class="auth-passkey-error mt-4" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@unless($passkeysEnabled)<p class="mt-4 text-sm text-slate-500">管理员暂未开启通行密钥登录。已有密钥仍可管理，开启后可继续使用。</p>@endunless
<div class="auth-passkey-list">
@forelse($passkeys as $key)
    <details class="auth-passkey-item"><summary><span class="auth-settings__icon"><i data-lucide="fingerprint"></i></span><span class="auth-passkey-item__text"><strong>{{ $key->name }}</strong><small>{{ $key->rp_id }} · {{ $key->last_used_at ? '最近使用 '.$key->last_used_at->timezone(config('kexi.display_timezone'))->format('Y/m/d H:i') : '尚未用于登录' }}</small></span><span class="auth-passkey-item__manage">管理<i data-lucide="chevron-down"></i></span></summary>
        <form class="auth-passkey-item__form" method="POST" action="{{ route('passkeys.rename',$key) }}">@csrf
            <label class="wb-field-group"><span class="wb-label">密钥名称</span><input class="wb-field" name="name" value="{{ $key->name }}" required maxlength="80"></label>
            <label class="wb-field-group"><span class="wb-label">当前登录密码</span><input class="wb-field" type="password" name="password" required autocomplete="current-password" placeholder="验证是你本人在操作"></label>
            <div class="flex flex-wrap gap-3"><button class="wb-btn" type="submit" name="_method" value="PATCH">保存名称</button><button class="wb-btn text-red-700" type="submit" name="_method" value="DELETE" onclick="return confirm('移除此通行密钥？之后仍可用密码登录。')">移除密钥</button></div>
        </form>
    </details>
@empty
    <div class="auth-passkey-empty"><i data-lucide="fingerprint"></i><div><strong>还没有通行密钥</strong><p>添加后，下次登录可直接在设备上确认。</p></div></div>
@endforelse
</div>
@if($passkeysEnabled)
    <form class="auth-passkey-add" x-data="passkeyAction(@js(['mode'=>'register','options'=>route('passkeys.register.options',[],false),'finish'=>route('passkeys.register',[],false),'done'=>route('profile.edit',['passkey_added'=>1],false).'#profile-passkeys']))" x-on:submit.prevent="run()">
        <h3>添加新的通行密钥</h3><div class="auth-settings__grid"><label class="wb-field-group"><span class="wb-label">给密钥起个名字</span><input class="wb-field" x-ref="keyName" maxlength="80" required placeholder="例如：我的手机、笔记本"></label><label class="wb-field-group"><span class="wb-label">当前登录密码</span><input class="wb-field" x-ref="keyPassword" type="password" required autocomplete="current-password" placeholder="添加前先验证密码"></label></div>
        <p class="auth-passkey-error" x-cloak x-show="error" x-text="error" role="alert"></p>
        <p class="auth-settings__help" x-cloak x-show="!supported">当前浏览器或连接不支持通行密钥，请在 HTTPS 网站使用新版浏览器。</p>
        <button class="wb-btn wb-btn--primary" type="submit" x-bind:disabled="busy || !supported"><i data-lucide="plus"></i><span x-text="busy ? '请在设备上确认…' : '添加通行密钥'">添加通行密钥</span></button>
    </form>
@endif

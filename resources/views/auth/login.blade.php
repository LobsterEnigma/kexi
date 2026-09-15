<x-guest-layout>
    <x-slot name="title">登录</x-slot>

    <div class="border-b border-slate-200 px-6 py-6 sm:px-8">
        <h1 class="text-xl font-bold leading-7 text-slate-900">登录{{ config('app.name', '课隙') }}</h1>
        <p class="mt-1 text-sm leading-6 text-slate-600">继续整理你的课程与周间安排。</p>
    </div>

    @if (session('status'))
        <div class="mx-6 mt-5 flex items-start gap-2 rounded-md border border-green-200 bg-green-50 px-3 py-2.5 text-sm text-green-800 sm:mx-8" role="status">
            <i data-lucide="circle-check-big" class="mt-0.5 h-4 w-4 shrink-0"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" x-data="passkeyAction(@js(['mode'=>'login','options'=>route('passkeys.login.options',[],false),'finish'=>route('passkeys.login',[],false)]))">
        @csrf

        <div class="space-y-4 px-6 py-6 sm:px-8">
            <label class="wb-field-group">
                <span class="wb-label">邮箱</span>
                <input class="wb-field" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="name@example.com">
                @error('email')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="wb-field-group">
                <span class="wb-label">密码</span>
                <input class="wb-field" type="password" name="password" required autocomplete="current-password" placeholder="请输入密码">
                @error('password')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <x-turnstile action="login" />
            <div class="flex items-center justify-between gap-4">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" type="checkbox" name="remember">
                    保持登录
                </label>

                @if (Route::has('password.request'))
                    <a class="text-sm font-medium text-blue-700 hover:text-blue-900" href="{{ route('password.request') }}">忘记密码？</a>
                @endif
            </div>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
            <button class="wb-btn wb-btn--primary w-full" type="submit">
                登录
                <i data-lucide="chevron-right"></i>
            </button>

            @if(app(\App\Services\SiteSettings::class)->bool('passkeys_enabled'))
                <button class="wb-btn mt-3 w-full" type="button" x-on:click="run()" x-bind:disabled="busy || !supported"><i data-lucide="fingerprint"></i><span x-text="busy ? '请在设备上确认…' : '使用通行密钥登录'">使用通行密钥登录</span></button>
                <p class="mt-2 text-center text-xs leading-5 text-slate-500">已添加密钥？无需填写邮箱和密码，在设备上确认即可。</p>
                <p class="mt-2 text-xs text-slate-500" x-cloak x-show="!supported">当前浏览器或连接不支持通行密钥，请使用密码登录。</p>
                <p class="auth-passkey-error mt-3" x-cloak x-show="error" x-text="error" role="alert"></p>
            @endif

            @if (Route::has('register'))
                <p class="mt-3 text-center text-sm text-slate-600">
                    还没有账户？
                    <a class="font-semibold text-blue-700 hover:text-blue-900" href="{{ route('register') }}">创建账户</a>
                </p>
            @endif
        </div>
    </form>
</x-guest-layout>

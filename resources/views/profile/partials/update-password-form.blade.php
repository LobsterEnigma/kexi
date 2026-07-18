<section>
    <header>
        <h2 class="text-lg font-bold leading-7 text-slate-900">登录密码</h2>
        <p class="mt-1 text-sm leading-6 text-slate-600">建议使用与其他网站不同的长密码。</p>
    </header>

    <form class="mt-6 max-w-xl space-y-5" method="POST" action="{{ route('password.update') }}">
        @csrf
        @method('PUT')

        <label class="wb-field-group block">
            <span class="wb-label">当前密码</span>
            <input class="wb-field" type="password" name="current_password" autocomplete="current-password">
            @foreach ($errors->updatePassword->get('current_password') as $message)<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@endforeach
        </label>

        <label class="wb-field-group block">
            <span class="wb-label">新密码</span>
            <input class="wb-field" type="password" name="password" autocomplete="new-password">
            @foreach ($errors->updatePassword->get('password') as $message)<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@endforeach
        </label>

        <label class="wb-field-group block">
            <span class="wb-label">确认新密码</span>
            <input class="wb-field" type="password" name="password_confirmation" autocomplete="new-password">
            @foreach ($errors->updatePassword->get('password_confirmation') as $message)<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@endforeach
        </label>

        <div class="flex items-center gap-3">
            <button class="wb-btn wb-btn--primary" type="submit"><i data-lucide="check"></i>更新密码</button>
            @if (session('status') === 'password-updated')
                <span class="text-sm text-green-700" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2200)" x-transition>密码已更新</span>
            @endif
        </div>
    </form>
</section>

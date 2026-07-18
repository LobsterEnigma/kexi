<section>
    <header>
        <h2 class="text-lg font-bold leading-7 text-slate-900">个人资料</h2>
        <p class="mt-1 text-sm leading-6 text-slate-600">更新用于登录和识别账户的姓名与邮箱。</p>
    </header>

    <form id="send-verification" method="POST" action="{{ route('verification.send') }}">@csrf</form>

    <form class="mt-6 max-w-xl space-y-5" method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')

        <label class="wb-field-group block">
            <span class="wb-label">姓名</span>
            <input class="wb-field" type="text" name="name" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @error('name')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        <label class="wb-field-group block">
            <span class="wb-label">邮箱</span>
            <input class="wb-field" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-3 text-sm leading-5 text-amber-900">
                当前邮箱尚未验证。
                <button class="font-semibold underline" type="submit" form="send-verification">重新发送验证邮件</button>
                @if (session('status') === 'verification-link-sent')
                    <span class="mt-1 block text-green-700">新的验证链接已发送。</span>
                @endif
            </div>
        @endif

        <div class="flex items-center gap-3">
            <button class="wb-btn wb-btn--primary" type="submit"><i data-lucide="check"></i>保存资料</button>
            @if (session('status') === 'profile-updated')
                <span class="text-sm text-green-700" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2200)" x-transition>已保存</span>
            @endif
        </div>
    </form>
</section>

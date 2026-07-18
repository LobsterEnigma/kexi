<x-guest-layout>
    <x-slot name="title">创建账户</x-slot>

    <div class="border-b border-slate-200 px-6 py-6 sm:px-8">
        <h1 class="text-xl font-bold leading-7 text-slate-900">创建{{ config('app.name', '课隙') }}账户</h1>
        <p class="mt-1 text-sm leading-6 text-slate-600">注册后会自动建立一张默认学期课表。</p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="space-y-4 px-6 py-6 sm:px-8">
            <label class="wb-field-group">
                <span class="wb-label">姓名</span>
                <input class="wb-field" type="text" name="name" value="{{ old('name') }}" maxlength="255" required autofocus autocomplete="name" placeholder="你的姓名">
                @error('name')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="wb-field-group">
                <span class="wb-label">邮箱</span>
                <input class="wb-field" type="email" name="email" value="{{ old('email') }}" maxlength="255" required autocomplete="username" placeholder="name@example.com">
                @error('email')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="wb-field-group">
                <span class="wb-label">密码</span>
                <input class="wb-field" type="password" name="password" required autocomplete="new-password" placeholder="设置一个安全密码">
                @error('password')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="wb-field-group">
                <span class="wb-label">确认密码</span>
                <input class="wb-field" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="再次输入密码">
            </label>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
            <button class="wb-btn wb-btn--primary w-full" type="submit">
                创建账户
                <i data-lucide="chevron-right"></i>
            </button>
            <p class="mt-3 text-center text-sm text-slate-600">
                已有账户？
                <a class="font-semibold text-blue-700 hover:text-blue-900" href="{{ route('login') }}">返回登录</a>
            </p>
        </div>
    </form>
</x-guest-layout>

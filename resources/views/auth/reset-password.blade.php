<x-guest-layout>
    <x-slot name="title">重置密码</x-slot>

    <div class="border-b border-slate-200 px-6 py-6 sm:px-8">
        <h1 class="text-xl font-bold leading-7 text-slate-900">设置新密码</h1>
        <p class="mt-1 text-sm leading-6 text-slate-600">完成后即可使用新密码登录{{ config('app.name', '课隙') }}。</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="space-y-4 px-6 py-6 sm:px-8">
            <label class="wb-field-group">
                <span class="wb-label">邮箱</span>
                <input class="wb-field" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
                @error('email')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="wb-field-group">
                <span class="wb-label">新密码</span>
                <input class="wb-field" type="password" name="password" required autocomplete="new-password">
                @error('password')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="wb-field-group">
                <span class="wb-label">确认新密码</span>
                <input class="wb-field" type="password" name="password_confirmation" required autocomplete="new-password">
            </label>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
            <button class="wb-btn wb-btn--primary w-full" type="submit">保存新密码</button>
        </div>
    </form>
</x-guest-layout>

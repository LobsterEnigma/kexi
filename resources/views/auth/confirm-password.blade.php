<x-guest-layout>
    <x-slot name="title">确认密码</x-slot>

    <div class="border-b border-slate-200 px-6 py-6 sm:px-8">
        <h1 class="text-xl font-bold leading-7 text-slate-900">确认你的身份</h1>
        <p class="mt-1 text-sm leading-6 text-slate-600">这是敏感操作，请再次输入当前密码。</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="px-6 py-6 sm:px-8">
            <label class="wb-field-group">
                <span class="wb-label">当前密码</span>
                <input class="wb-field" type="password" name="password" required autofocus autocomplete="current-password">
                @error('password')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
            <button class="wb-btn wb-btn--primary w-full" type="submit">确认并继续</button>
        </div>
    </form>
</x-guest-layout>

<x-guest-layout>
    <x-slot name="title">找回密码</x-slot>

    <div class="border-b border-slate-200 px-6 py-6 sm:px-8">
        <h1 class="text-xl font-bold leading-7 text-slate-900">找回密码</h1>
        <p class="mt-1 text-sm leading-6 text-slate-600">输入注册邮箱，我们会发送重置密码链接。</p>
    </div>

    @if (session('status'))
        <div class="mx-6 mt-5 flex items-start gap-2 rounded-md border border-green-200 bg-green-50 px-3 py-2.5 text-sm text-green-800 sm:mx-8" role="status">
            <i data-lucide="circle-check-big" class="mt-0.5 h-4 w-4 shrink-0"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="px-6 py-6 sm:px-8">
            <label class="wb-field-group">
                <span class="wb-label">邮箱</span>
                <input class="wb-field" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="name@example.com">
                @error('email')<span class="mt-1.5 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
            <button class="wb-btn wb-btn--primary w-full" type="submit">发送重置链接</button>
            <p class="mt-3 text-center text-sm"><a class="font-semibold text-blue-700 hover:text-blue-900" href="{{ route('login') }}">返回登录</a></p>
        </div>
    </form>
</x-guest-layout>

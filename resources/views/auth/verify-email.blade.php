<x-guest-layout>
    <x-slot name="title">验证邮箱</x-slot>

    <div class="px-6 py-7 text-center sm:px-8">
        <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-md bg-blue-50 text-blue-700">
            <i data-lucide="file-text" class="h-6 w-6"></i>
        </span>
        <h1 class="mt-4 text-xl font-bold leading-7 text-slate-900">请验证你的邮箱</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">我们已发送验证链接。完成验证后即可进入课表工作台。</p>

        @if (session('status') === 'verification-link-sent')
            <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2.5 text-sm text-green-800" role="status">新的验证链接已发送。</div>
        @endif

        <form class="mt-6" method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="wb-btn wb-btn--primary w-full" type="submit">重新发送验证邮件</button>
        </form>

        <form class="mt-3" method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="wb-btn w-full" type="submit">退出登录</button>
        </form>
    </div>
</x-guest-layout>

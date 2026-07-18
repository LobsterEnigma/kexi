<x-guest-layout>
    <x-slot name="title">暂不开放注册</x-slot>

    <div class="px-6 py-8 text-center sm:px-8">
        <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-md bg-amber-50 text-amber-700">
            <i data-lucide="user-round" class="h-6 w-6"></i>
        </span>
        <h1 class="mt-4 text-xl font-bold leading-7 text-slate-900">暂不开放新用户注册</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">管理员当前关闭了注册入口。已有账户仍可正常登录。</p>
        <a class="wb-btn wb-btn--primary mt-6 w-full" href="{{ route('login') }}">
            返回登录
            <i data-lucide="chevron-right"></i>
        </a>
    </div>
</x-guest-layout>

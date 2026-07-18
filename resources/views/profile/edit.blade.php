<x-app-layout>
    <x-slot name="title">账户设置</x-slot>

    <div class="min-h-[100dvh] bg-slate-50">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-[72px] max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <a class="flex items-center gap-3" href="{{ route('dashboard') }}">
                    <x-brand-mark />
                    <span class="text-xl font-bold text-slate-900">{{ config('app.name', '课隙') }}</span>
                </a>
                <a class="wb-btn" href="{{ route('dashboard') }}">
                    <i data-lucide="chevron-left"></i>
                    返回课表
                </a>
            </div>
        </header>

        <main class="mx-auto grid max-w-6xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:py-10">
            <aside>
                <p class="text-xs font-semibold uppercase text-slate-500">账户</p>
                <h1 class="mt-2 text-2xl font-bold leading-8 text-slate-900">账户设置</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">管理个人资料、登录密码与账户数据。</p>

                <nav class="mt-6 space-y-1" aria-label="账户设置目录">
                    <a class="wb-nav__item" href="#profile-information"><i data-lucide="user-round"></i><span>个人资料</span></a>
                    <a class="wb-nav__item" href="#profile-password"><i data-lucide="settings"></i><span>登录密码</span></a>
                    <a class="wb-nav__item text-red-700" href="#profile-delete"><i data-lucide="trash-2"></i><span>删除账户</span></a>
                </nav>

                <form class="mt-6 border-t border-slate-200 pt-5" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="wb-btn w-full" type="submit"><i data-lucide="log-out"></i>退出登录</button>
                </form>
            </aside>

            <div class="space-y-6">
                <div id="profile-information" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <div id="profile-password" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    @include('profile.partials.update-password-form')
                </div>

                <div id="profile-delete" class="scroll-mt-6 rounded-lg border border-red-200 bg-white p-5 shadow-sm sm:p-7">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </main>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="title">访问共享课表</x-slot>

    <main class="flex min-h-[100dvh] items-center justify-center bg-slate-50 px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 flex items-center justify-center gap-3">
                <span class="wb-brand__mark bg-white" aria-hidden="true">
                    <i data-lucide="book-open" class="h-5 w-5"></i>
                </span>
                <span class="text-2xl font-bold text-slate-900">{{ config('app.name', '课隙') }}</span>
            </div>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="px-6 pb-5 pt-7 text-center sm:px-8">
                    <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                        <i data-lucide="eye" class="h-6 w-6"></i>
                    </span>
                    <h1 class="mt-4 text-xl font-bold leading-7 text-slate-900">这是受保护的共享课表</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        输入访问密码后查看“{{ $share->timetable->name }}”。解锁状态会保留 12 小时。
                    </p>
                </div>

                @if ($errors->any())
                    <div class="wb-alert !mx-6 !mt-0 sm:!mx-8" role="alert">
                        <i data-lucide="triangle-alert"></i>
                        <span>{{ $errors->first('password') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('public-shares.unlock', $token) }}">
                    @csrf
                    <div class="px-6 py-5 sm:px-8">
                        <label class="wb-field-group">
                            <span class="wb-label">访问密码</span>
                            <input class="wb-field" type="password" name="password" maxlength="100" autocomplete="current-password" required autofocus placeholder="请输入分享者设置的密码">
                        </label>
                    </div>

                    <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">
                        <button class="wb-btn wb-btn--primary w-full" type="submit">
                            解锁课表
                            <i data-lucide="chevron-right"></i>
                        </button>
                    </div>
                </form>
            </section>

            <p class="mt-5 text-center text-xs leading-5 text-slate-500">该页面不会出现在搜索结果中，且不会缓存课表内容。</p>
        </div>
    </main>
</x-app-layout>

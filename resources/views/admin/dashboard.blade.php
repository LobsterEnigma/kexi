<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase text-gray-500">管理控制台</p>
                <h1 class="text-xl font-semibold text-gray-900">系统概览</h1>
            </div>
            <span class="text-sm text-gray-500">{{ now()->timezone(config('kexi.display_timezone'))->format('Y-m-d H:i') }}</span>
        </div>
    </x-slot>

    @include('admin.partials.navigation')

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @include('admin.partials.feedback')

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" aria-label="系统统计">
            @foreach ([
                '用户总数' => $stats['users'],
                '审查中用户' => $stats['review_users'],
                '已封禁用户' => $stats['banned_users'],
                '课表总数' => $stats['timetables'],
                '可用分享' => $stats['active_shares'],
            ] as $label => $value)
                <div class="rounded-md border border-gray-200 bg-white p-4">
                    <p class="text-sm text-gray-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </section>

        <section class="mt-6 border-y border-gray-200 bg-white px-4 py-5 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">全局服务状态</h2>
                    <p class="mt-1 text-sm text-gray-500">开关变更会立即影响新请求。</p>
                </div>
                <div class="flex gap-3 text-sm">
                    <span class="border px-3 py-1.5 {{ $registrationEnabled ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-gray-300 bg-gray-100 text-gray-700' }}">
                        注册：{{ $registrationEnabled ? '开启' : '关闭' }}
                    </span>
                    <span class="border px-3 py-1.5 {{ $sharingEnabled ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-gray-300 bg-gray-100 text-gray-700' }}">
                        分享：{{ $sharingEnabled ? '开启' : '关闭' }}
                    </span>
                </div>
            </div>
        </section>

        <section class="mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">最近审计记录</h2>
                <a href="{{ route('admin.audits.index') }}" class="text-sm font-medium text-gray-700 underline hover:text-gray-950">查看全部</a>
            </div>
            <div class="overflow-x-auto border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">时间</th>
                            <th class="px-4 py-3">管理员</th>
                            <th class="px-4 py-3">操作</th>
                            <th class="px-4 py-3">目标</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($latestAudits as $audit)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ $audit->created_at?->timezone(config('kexi.display_timezone'))->format('m-d H:i:s') }}</td>
                                <td class="px-4 py-3 text-gray-800">{{ $audit->actor?->email ?? '系统' }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $audit->action }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ class_basename((string) $audit->target_type) }}{{ $audit->target_id ? '#'.$audit->target_id : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">暂无审计记录</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>

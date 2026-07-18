<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase text-gray-500">管理控制台</p>
            <h1 class="text-xl font-semibold text-gray-900">审计日志</h1>
        </div>
    </x-slot>

    @include('admin.partials.navigation')

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @include('admin.partials.feedback')

        <form method="GET" action="{{ route('admin.audits.index') }}" class="mb-4 flex max-w-xl gap-2">
            <label for="audit-search" class="sr-only">搜索审计日志</label>
            <input id="audit-search" name="q" value="{{ $search }}" maxlength="100" placeholder="按操作、管理员或邮箱搜索"
                   class="min-w-0 flex-1 border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500">
            <button class="border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">搜索</button>
        </form>

        <div class="overflow-x-auto border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">时间</th>
                        <th class="px-4 py-3">管理员</th>
                        <th class="px-4 py-3">操作</th>
                        <th class="px-4 py-3">目标</th>
                        <th class="px-4 py-3">来源</th>
                        <th class="px-4 py-3">详情</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($audits as $audit)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-4 text-gray-500">{{ $audit->created_at?->timezone(config('kexi.display_timezone'))->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-4">
                                <div class="text-gray-900">{{ $audit->actor?->name ?? '系统' }}</div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ $audit->actor?->email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold text-gray-900">{{ $audit->action }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-gray-600">
                                {{ class_basename((string) $audit->target_type) }}{{ $audit->target_id ? '#'.$audit->target_id : '' }}
                            </td>
                            <td class="px-4 py-4 text-xs text-gray-500">
                                <div>{{ $audit->ip_address }}</div>
                                <div class="mt-1 max-w-64 truncate" title="{{ $audit->user_agent }}">{{ $audit->user_agent }}</div>
                            </td>
                            <td class="min-w-64 px-4 py-4">
                                <details>
                                    <summary class="cursor-pointer text-xs font-semibold text-gray-700 underline">查看变更</summary>
                                    <div class="mt-2 grid gap-2 text-xs">
                                        <div>
                                            <p class="font-semibold text-gray-600">变更前</p>
                                            <pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap break-all bg-gray-50 p-2 text-gray-700">{{ json_encode($audit->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-600">变更后</p>
                                            <pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap break-all bg-gray-50 p-2 text-gray-700">{{ json_encode($audit->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">暂无审计日志</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $audits->links() }}</div>
    </div>
</x-app-layout>

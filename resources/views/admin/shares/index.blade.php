<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase text-gray-500">管理控制台</p>
            <h1 class="text-xl font-semibold text-gray-900">分享治理</h1>
        </div>
    </x-slot>

    @include('admin.partials.navigation')

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @include('admin.partials.feedback')

        <form method="GET" action="{{ route('admin.shares.index') }}" class="mb-4 flex max-w-xl gap-2">
            <label for="share-search" class="sr-only">搜索分享</label>
            <input id="share-search" name="q" value="{{ $search }}" maxlength="100" placeholder="按标签、课表、用户或邮箱搜索"
                   class="min-w-0 flex-1 border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500">
            <button class="border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">搜索</button>
        </form>

        <div class="overflow-x-auto border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">分享</th>
                        <th class="px-4 py-3">所属用户</th>
                        <th class="px-4 py-3">有效期</th>
                        <th class="px-4 py-3">状态</th>
                        <th class="px-4 py-3">访问</th>
                        <th class="px-4 py-3 text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($shares as $share)
                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <div class="font-semibold text-gray-900">{{ $share->label ?: '未命名分享' }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $share->timetable->name }} · #{{ $share->id }}</div>
                                @if ($share->hasPassword())
                                    <span class="mt-1 inline-block border border-gray-300 bg-gray-50 px-1.5 py-0.5 text-xs text-gray-600">密码保护</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-gray-900">{{ $share->timetable->user->name }}</div>
                                <div class="mt-0.5 text-xs text-gray-500">{{ $share->timetable->user->email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-gray-600">
                                {{ $share->expires_at?->timezone(config('kexi.display_timezone'))->format('Y-m-d H:i') ?? '不过期' }}
                            </td>
                            <td class="px-4 py-4">
                                @if ($share->revoked_at)
                                    <span class="font-semibold text-red-700">已撤销</span>
                                @elseif ($share->disabled_by_admin_at)
                                    <span class="font-semibold text-amber-700">已暂停</span>
                                    <p class="mt-1 max-w-xs text-xs text-gray-500">{{ $share->disabled_reason }}</p>
                                @elseif (! $sharingEnabled)
                                    <span class="font-semibold text-amber-700">全局暂停</span>
                                @elseif ($share->timetable->user->banned_at)
                                    <span class="font-semibold text-red-700">所有者已封禁</span>
                                @elseif ($share->timetable->user->sharing_disabled_at)
                                    <span class="font-semibold text-amber-700">用户分享已停用</span>
                                @elseif ($share->expires_at && $share->expires_at->isPast())
                                    <span class="font-medium text-gray-500">已过期</span>
                                @else
                                    <span class="font-medium text-emerald-700">可用</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-gray-600">
                                <div>{{ number_format($share->views_count) }} 次</div>
                                <div class="mt-1 text-xs text-gray-400">{{ $share->last_viewed_at?->timezone(config('kexi.display_timezone'))->format('m-d H:i') ?? '尚无访问' }}</div>
                            </td>
                            <td class="min-w-64 px-4 py-4 text-right">
                                @if (! $share->revoked_at)
                                    <div class="flex flex-col items-end gap-2">
                                        @if ($share->disabled_by_admin_at)
                                            <form method="POST" action="{{ route('admin.shares.enable', $share) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-50">恢复分享</button>
                                            </form>
                                        @else
                                            <details class="w-full border border-gray-200 bg-gray-50 text-left">
                                                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-amber-800">暂停分享</summary>
                                                <form method="POST" action="{{ route('admin.shares.disable', $share) }}" class="border-t border-gray-200 p-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="block text-xs font-medium text-gray-700" for="disable-reason-{{ $share->id }}">暂停原因</label>
                                                    <textarea id="disable-reason-{{ $share->id }}" name="reason" required maxlength="255" rows="2"
                                                              class="mt-1 w-full border-gray-300 text-xs focus:border-gray-500 focus:ring-gray-500"></textarea>
                                                    <button class="mt-2 bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800">确认暂停</button>
                                                </form>
                                            </details>
                                        @endif

                                        <details class="w-full border border-red-200 bg-red-50 text-left">
                                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-red-700">永久撤销</summary>
                                            <form method="POST" action="{{ route('admin.shares.revoke', $share) }}" class="border-t border-red-200 p-3">
                                                @csrf
                                                @method('DELETE')
                                                <p class="text-xs text-red-800">撤销后无法恢复，需由用户创建新链接。</p>
                                                <button class="mt-2 bg-red-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-800">确认撤销</button>
                                            </form>
                                        </details>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">未找到分享记录</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $shares->links() }}</div>
    </div>
</x-app-layout>

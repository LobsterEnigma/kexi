<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase text-gray-500">管理控制台</p>
            <h1 class="text-xl font-semibold text-gray-900">用户与访问权限</h1>
        </div>
    </x-slot>

    @include('admin.partials.navigation')

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @include('admin.partials.feedback')

        <form method="GET" action="{{ route('admin.users.index') }}" class="mb-5 flex max-w-3xl flex-col gap-2 sm:flex-row">
            <label for="user-search" class="sr-only">搜索用户</label>
            <input id="user-search" name="q" value="{{ $search }}" maxlength="100" placeholder="按姓名或邮箱搜索"
                   class="min-w-0 flex-1 border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500">
            <label for="user-status" class="sr-only">账户状态</label>
            <select id="user-status" name="status" class="border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500">
                <option value="all" @selected($statusFilter === 'all')>全部状态</option>
                <option value="normal" @selected($statusFilter === 'normal')>正常</option>
                <option value="review" @selected($statusFilter === 'review')>审查中</option>
                <option value="banned" @selected($statusFilter === 'banned')>已封禁</option>
            </select>
            <button class="border border-slate-800 bg-slate-800 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-700">筛选</button>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="min-w-[940px] divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">用户</th>
                        <th class="px-4 py-3">课表</th>
                        <th class="px-4 py-3">账户状态</th>
                        <th class="px-4 py-3">分享状态</th>
                        <th class="px-4 py-3 text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <div class="font-semibold text-gray-900">{{ $user->name }}</div>
                                <div class="mt-0.5 text-gray-500">{{ $user->email }}</div>
                                <div class="mt-1 flex gap-2 text-xs">
                                    <span class="text-gray-400">#{{ $user->id }}</span>
                                    @if ($user->is_admin)
                                        <span class="border border-gray-300 bg-gray-100 px-1.5 py-0.5 font-medium text-gray-700">管理员</span>
                                    @endif
                                    @if ($user->is(auth()->user()))
                                        <span class="text-gray-500">当前账户</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-gray-700">{{ $user->timetables_count }}</td>
                            <td class="px-4 py-4">
                                @if ($user->banned_at)
                                    <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">已封禁</span>
                                    <p class="mt-1 max-w-xs text-xs text-gray-500">{{ $user->ban_reason }}</p>
                                    <p class="mt-1 text-xs text-gray-400">{{ $user->banned_at->timezone(config('kexi.display_timezone'))->format('Y-m-d H:i') }}</p>
                                @elseif ($user->review_started_at)
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">审查中</span>
                                    <p class="mt-1 max-w-xs text-xs text-gray-500">{{ $user->review_reason }}</p>
                                    <p class="mt-1 text-xs text-gray-400">{{ $user->review_started_at->timezone(config('kexi.display_timezone'))->format('Y-m-d H:i') }}</p>
                                @else
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">正常</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if ($user->banned_at)
                                    <span class="font-medium text-slate-500">随账户停用</span>
                                @elseif ($user->review_started_at)
                                    <span class="font-medium text-amber-700">审查期间暂停</span>
                                @elseif ($user->sharing_disabled_at)
                                    <span class="font-semibold text-amber-700">已停用</span>
                                    <p class="mt-1 max-w-xs text-xs text-gray-500">{{ $user->sharing_disabled_reason }}</p>
                                    <p class="mt-1 text-xs text-gray-400">来源：{{ $user->sharing_disabled_source === 'admin' ? '管理员' : '用户' }}</p>
                                @else
                                    <span class="font-medium text-emerald-700">可用</span>
                                @endif
                            </td>
                            <td class="min-w-64 px-4 py-4 text-right">
                                <div class="flex flex-col items-end gap-2">
                                    @if ($user->is(auth()->user()))
                                        <div class="w-full border border-blue-100 bg-blue-50 px-3 py-2 text-left text-xs leading-5 text-blue-800">
                                            当前登录账户不能审查或封禁
                                        </div>
                                    @elseif ($user->banned_at)
                                        <form method="POST" action="{{ route('admin.users.unban', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-50">解封用户</button>
                                        </form>
                                    @else
                                        @if ($user->review_started_at)
                                            <form method="POST" action="{{ route('admin.users.review.clear', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">结束审查</button>
                                            </form>
                                        @else
                                            <details class="w-full border border-amber-200 bg-amber-50 text-left">
                                                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-amber-900">设为审查中</summary>
                                                <form method="POST" action="{{ route('admin.users.review.start', $user) }}" class="border-t border-amber-200 p-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="block text-xs font-medium text-gray-700" for="review-reason-{{ $user->id }}">审查原因（用户登录时可见）</label>
                                                    <textarea id="review-reason-{{ $user->id }}" name="reason" required maxlength="255" rows="2"
                                                              class="mt-1 w-full border-gray-300 text-xs focus:border-amber-600 focus:ring-amber-600"></textarea>
                                                    <button class="mt-2 bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800">开始审查</button>
                                                </form>
                                            </details>
                                        @endif

                                        <details class="w-full border border-gray-200 bg-gray-50 text-left">
                                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-red-700">{{ $user->review_started_at ? '升级为封禁' : '封禁用户' }}</summary>
                                            <form method="POST" action="{{ route('admin.users.ban', $user) }}" class="border-t border-gray-200 p-3">
                                                @csrf
                                                @method('PATCH')
                                                <label class="block text-xs font-medium text-gray-700" for="ban-reason-{{ $user->id }}">封禁原因（用户登录时可见）</label>
                                                <textarea id="ban-reason-{{ $user->id }}" name="reason" required maxlength="255" rows="2"
                                                          class="mt-1 w-full border-gray-300 text-xs focus:border-gray-500 focus:ring-gray-500"></textarea>
                                                <button class="mt-2 bg-red-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-800">确认封禁</button>
                                            </form>
                                        </details>
                                    @endif

                                    @unless ($user->isAccessSuspended())
                                        @if ($user->sharing_disabled_at && $user->sharing_disabled_source === 'admin')
                                            <form method="POST" action="{{ route('admin.users.sharing.enable', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-50">恢复用户分享</button>
                                            </form>
                                        @elseif (! $user->sharing_disabled_at)
                                            <details class="w-full border border-gray-200 bg-gray-50 text-left">
                                                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-amber-800">停用用户分享</summary>
                                                <form method="POST" action="{{ route('admin.users.sharing.disable', $user) }}" class="border-t border-gray-200 p-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="block text-xs font-medium text-gray-700" for="sharing-reason-{{ $user->id }}">停用原因（用户创建分享时可见）</label>
                                                    <textarea id="sharing-reason-{{ $user->id }}" name="reason" required maxlength="255" rows="2"
                                                              class="mt-1 w-full border-gray-300 text-xs focus:border-gray-500 focus:ring-gray-500"></textarea>
                                                    <button class="mt-2 bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800">确认停用</button>
                                                </form>
                                            </details>
                                        @endif
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">未找到用户</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    </div>
</x-app-layout>

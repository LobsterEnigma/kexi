<nav class="border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 py-2 sm:px-6 lg:px-8">
        @foreach ([
            'admin.dashboard' => '概览',
            'admin.users.index' => '用户',
            'admin.shares.index' => '分享',
            'admin.settings.edit' => '系统设置',
            'admin.audits.index' => '审计日志',
        ] as $route => $label)
            <a href="{{ route($route) }}"
               class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium {{ request()->routeIs($route) ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>

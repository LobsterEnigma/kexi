@props([
    'timetable',
    'timetables' => collect(),
])

@php
    $plans = collect($timetables);
    $user = auth()->user();
@endphp

<div class="wb-sidebar h-full">
    <a class="wb-brand" href="{{ route('timetables.show', $timetable) }}" aria-label="返回当前课表">
        <x-brand-mark />
        <span class="wb-brand__text">{{ config('app.name', '课隙') }}</span>
    </a>

    <nav class="wb-nav" aria-label="工作台导航">
        <a class="wb-nav__item" href="{{ route('timetables.show', $timetable) }}" aria-current="page" title="我的课表">
            <i data-lucide="calendar-days"></i>
            <span class="wb-nav__label">我的课表</span>
        </a>

        <button class="wb-nav__item" type="button" x-on:click="openDialog('share')" title="分享管理">
            <i data-lucide="share-2"></i>
            <span class="wb-nav__label">分享管理</span>
        </button>

        <a class="wb-nav__item" href="{{ route('profile.edit') }}" title="账户设置">
            <i data-lucide="user-cog"></i>
            <span class="wb-nav__label">账户设置</span>
        </a>
    </nav>

    <section class="wb-sidebar__section" aria-labelledby="plan-list-title">
        <div class="wb-sidebar__section-title">
            <span id="plan-list-title">我的课表方案</span>
            <button
                class="wb-icon-btn !h-7 !w-7 !border-transparent"
                type="button"
                x-on:click="openDialog('timetable-create')"
                title="新建课表方案"
                aria-label="新建课表方案"
            >
                <i data-lucide="plus" class="!h-[18px] !w-[18px]"></i>
            </button>
        </div>

        <div class="wb-plan-list">
            @forelse ($plans as $plan)
                <a
                    class="wb-plan"
                    href="{{ route('timetables.show', $plan) }}"
                    aria-current="{{ (string) $plan->getKey() === (string) $timetable->getKey() ? 'true' : 'false' }}"
                    title="{{ trim(($plan->term_name ? $plan->term_name.' · ' : '').$plan->name) }}"
                >
                    <span class="wb-plan__dot" aria-hidden="true"></span>
                    <span class="truncate">{{ $plan->term_name ? $plan->term_name.' · ' : '' }}{{ $plan->name }}</span>
                </a>
            @empty
                <button class="wb-plan" type="button" x-on:click="openDialog('timetable-create')">
                    <span class="wb-plan__dot" aria-hidden="true"></span>
                    <span>创建第一份课表</span>
                </button>
            @endforelse
        </div>
    </section>

    <a class="wb-account" href="{{ route('profile.edit') }}">
        <span class="wb-account__avatar" aria-hidden="true">
            <i data-lucide="user-round"></i>
        </span>
        <span class="wb-account__meta min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-slate-800">{{ $user?->name ?? '同学' }}</span>
            <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $user?->email ?? '账户设置' }}</span>
        </span>
        <i data-lucide="chevron-right" class="wb-account__chevron h-4 w-4 text-slate-500"></i>
    </a>
</div>

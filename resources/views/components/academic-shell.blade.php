@props(['timetable', 'title', 'subtitle' => null])
@php
    $timetables = auth()->user()->timetables()->orderByDesc('is_default')->orderByDesc('updated_at')->get();
@endphp
<x-app-layout>
    <x-slot name="title">{{ $title }}</x-slot>
    <div class="wb-shell academic-workspace academic-page" x-data="academicWorkspace(@js(route('timetables.show', $timetable)))" x-on:keydown.escape.window="closeSidebar()">
        <aside class="wb-sidebar--desktop"><x-workbench.sidebar :timetable="$timetable" :timetables="$timetables" /></aside>
        <header class="wb-header">
            <div class="wb-header__top">
                <div class="wb-mobile-controls"><button class="wb-icon-btn" type="button" x-ref="navToggle" x-on:click="openSidebar()" aria-label="打开导航" x-bind:aria-expanded="sidebarOpen"><i data-lucide="menu"></i></button></div>
                <div class="wb-title" title="{{ $timetable->term_name }} · {{ $timetable->name }}">{{ $timetable->term_name ? $timetable->term_name.' · ' : '' }}{{ $timetable->name }}</div>
                <x-academic-reminders :timetable="$timetable" />
            </div>
        </header>
        <div class="academic-workspace__scroll">
        <div class="wb-records-main">
            <div class="academic-heading"><div><h1>{{ $title }}</h1><p>{{ $subtitle ?? ($timetable->name.' · '.$timetable->term_name) }}</p></div>{{ $actions ?? '' }}</div>
            @if(session('status'))<div class="academic-success" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())
                <section class="academic-errors" role="alert" aria-labelledby="academic-errors-title">
                    <span class="academic-errors__icon" aria-hidden="true"><i data-lucide="triangle-alert"></i></span>
                    <div class="academic-errors__content">
                        <h2 id="academic-errors-title">请检查后再提交</h2>
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                </section>
            @endif
            {{ $slot }}
            <p class="wb-records-privacy"><i data-lucide="eye-off"></i>{{ request()->routeIs('personal-events.*')?'个人安排默认私密；公开分享与图片导出由你主动选择。':'学业任务与学习安排仅自己可见。' }}时间按 {{ $timetable->timezone }} 显示。</p>
        </div>
        </div>
        <div class="wb-drawer-backdrop" x-cloak x-show="sidebarOpen" x-on:click="closeSidebar()" x-transition.opacity></div>
        <aside class="wb-drawer wb-drawer--left" x-ref="navDrawer" x-cloak x-show="sidebarOpen" role="dialog" aria-modal="true" aria-label="工作台导航" x-on:keydown.tab="trapNavigation($event)">
            <button class="wb-icon-btn wb-drawer__close" type="button" x-on:click="closeSidebar()" aria-label="关闭导航"><i data-lucide="x"></i></button>
            <x-workbench.sidebar :timetable="$timetable" :timetables="$timetables" />
        </aside>
    </div>
</x-app-layout>

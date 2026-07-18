<x-app-layout>
    <x-slot name="title">创建课表</x-slot>

    <main class="min-h-[100dvh] bg-slate-50 px-4 py-8 sm:px-6 sm:py-12">
        <div class="mx-auto w-full max-w-3xl">
            <div class="mb-8 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <x-brand-mark />
                    <span class="text-2xl font-bold text-slate-900">{{ config('app.name', '课隙') }}</span>
                </div>

                <a class="wb-btn" href="{{ route('profile.edit') }}">
                    <i data-lucide="user-cog"></i>
                    账户
                </a>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-5 sm:px-8 sm:py-7">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                        <i data-lucide="calendar-range" class="h-6 w-6"></i>
                    </span>
                    <h1 class="mt-4 text-2xl font-bold leading-8 text-slate-900">创建你的第一张课表</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">先设置学期和提醒阈值，进入工作台后再添加课程时间段。</p>
                </div>

                @if ($errors->any())
                    <div class="wb-alert" role="alert">
                        <i data-lucide="triangle-alert"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('timetables.store') }}"
                    x-data="timetableTermRange({
                        startDate: @js(old('term_start_date')),
                        endDate: @js(old('term_end_date')),
                        weekCount: @js(old('week_count', 18)),
                    })"
                >
                    @csrf
                    <input type="hidden" name="_form" value="timetable-create">

                    <div class="p-5 sm:p-8">
                        <div class="wb-form-grid">
                            <label class="wb-field-group">
                                <span class="wb-label">方案名称</span>
                                <input class="wb-field" type="text" name="name" value="{{ old('name', '主课表') }}" maxlength="80" required autofocus>
                            </label>

                            <label class="wb-field-group">
                                <span class="wb-label">学期名称</span>
                                <input class="wb-field" type="text" name="term_name" value="{{ old('term_name') }}" maxlength="100" placeholder="例：2026 秋季">
                            </label>

                            <label class="wb-field-group">
                                <span class="wb-label">开学日期</span>
                                <input class="wb-field" type="date" name="term_start_date" x-model="startDate" x-on:change="startChanged()">
                            </label>

                            <label class="wb-field-group">
                                <span class="wb-label">学期截止日期</span>
                                <input class="wb-field" type="date" name="term_end_date" x-model="endDate" x-on:change="syncWeeksFromEnd()" x-bind:min="startDate || null">
                            </label>

                            <label class="wb-field-group">
                                <span class="wb-label">学期总周数</span>
                                <input class="wb-field" type="number" name="week_count" x-model.number="weekCount" x-on:change="syncEndFromWeeks()" min="1" max="30" required>
                            </label>

                            <label class="wb-field-group">
                                <span class="wb-label">临近课程提醒阈值</span>
                                <select class="wb-select" name="near_threshold_minutes" required>
                                    @foreach ([15, 30, 45, 60] as $minutes)
                                        <option value="{{ $minutes }}" @selected((int) old('near_threshold_minutes', 30) === $minutes)>{{ $minutes }} 分钟</option>
                                    @endforeach
                                </select>
                                <span class="wb-help">两门课程间隔不超过该时长时，工作台会标记为“临近”。</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-8">
                        <button class="wb-btn wb-btn--primary" type="submit">
                            创建并进入课表
                            <i data-lucide="chevron-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</x-app-layout>

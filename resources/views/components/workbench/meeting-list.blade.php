@props([
    'source' => 'createMeetings',
    'target' => 'create',
])

<div class="meeting-list">
    <template x-for="(meeting, index) in {{ $source }}" x-bind:key="meeting._key">
        <section
            class="meeting-editor"
            x-bind:class="{ 'meeting-editor--expanded': meeting._expanded }"
            data-meeting-editor
            x-bind:data-meeting-index="index"
        >
            <input type="hidden" x-bind:name="`meetings[${index}][id]`" x-bind:value="meeting.id || ''">
            <input type="hidden" x-bind:name="`meetings[${index}][color]`" x-bind:value="meeting.color">
            <div class="meeting-editor__header">
                <button
                    class="meeting-editor__toggle"
                    type="button"
                    x-on:click="toggleMeeting('{{ $target }}', index)"
                    x-bind:aria-expanded="meeting._expanded"
                    x-bind:aria-label="`${meeting._expanded ? '收起' : '展开'}时间段 ${index + 1}`"
                >
                    <span class="meeting-editor__title">
                        <i
                            data-lucide="chevron-down"
                            class="meeting-editor__chevron"
                            x-bind:class="{ 'meeting-editor__chevron--expanded': meeting._expanded }"
                        ></i>
                        <span class="wb-meeting-color" x-bind:style="`background-color: ${meeting.color}`" aria-hidden="true"></span>
                        <span x-text="`时间段 ${index + 1}`"></span>
                    </span>
                    <span
                        class="meeting-editor__summary"
                        x-show="!meeting._expanded"
                        x-text="meetingSummary(meeting)"
                    ></span>
                </button>
                <button
                    class="wb-icon-btn !h-7 !w-7 !border-transparent text-red-600"
                    type="button"
                    x-on:click="removeMeeting('{{ $target }}', index)"
                    x-bind:disabled="{{ $source }}.length === 1"
                    title="移除这个时间段"
                    aria-label="移除这个时间段"
                >
                    <i data-lucide="trash-2" class="!h-4 !w-4"></i>
                </button>
            </div>

            <div class="meeting-editor__body" x-cloak x-show="meeting._expanded">
                <div class="wb-form-grid">
                    <label class="wb-field-group">
                        <span class="wb-label">授课类型</span>
                        <input
                            class="wb-field"
                            type="text"
                            x-bind:name="`meetings[${index}][label]`"
                            x-model="meeting.label"
                            x-on:input="adoptTypeColor('{{ $target }}', index)"
                            list="meeting-types-{{ $target }}"
                            maxlength="40"
                            placeholder="例：Lecture / Tutorial"
                        >
                    </label>

                    <label class="wb-field-group">
                        <span class="wb-label">授课教师</span>
                        <input class="wb-field" type="text" x-bind:name="`meetings[${index}][teacher]`" x-model="meeting.teacher" placeholder="教师姓名">
                    </label>

                    <div class="wb-field-group wb-field-group--full">
                        <span class="wb-label" x-text="(meeting.label.trim() || '默认类型') + ' 颜色'"></span>
                        <div class="wb-color-picker">
                            <template x-for="color in colorPresets" x-bind:key="color">
                                <button type="button" class="wb-color-swatch" x-bind:style="`--swatch: ${color}`" x-bind:aria-label="`选择颜色 ${color}`" x-bind:title="color" x-bind:aria-pressed="meeting.color === color" x-on:click="setTypeColor('{{ $target }}', index, color)"><i data-lucide="check" x-show="meeting.color === color"></i></button>
                            </template>
                            <label class="wb-custom-color" title="自定义颜色">
                                <i data-lucide="palette"></i>
                                <input type="color" x-bind:value="meeting.color" x-on:input="setTypeColor('{{ $target }}', index, $event.target.value)" aria-label="自定义课程颜色">
                            </label>
                            <span class="wb-color-value" x-text="meeting.color.toUpperCase()"></span>
                        </div>
                    </div>

                    <label class="wb-field-group">
                        <span class="wb-label">星期</span>
                        <select
                            class="wb-select"
                            x-bind:name="`meetings[${index}][weekday]`"
                            x-model.number="meeting.weekday"
                            required
                        >
                            <option value="1">周一</option>
                            <option value="2">周二</option>
                            <option value="3">周三</option>
                            <option value="4">周四</option>
                            <option value="5">周五</option>
                            <option value="6">周六</option>
                            <option value="7">周日</option>
                        </select>
                    </label>

                    <div class="wb-field-group grid grid-cols-2 gap-2">
                        <label>
                            <span class="wb-label">开始</span>
                            <input
                                class="wb-field"
                                type="time"
                                x-bind:name="`meetings[${index}][starts_at]`"
                                x-model="meeting.starts_at"
                                required
                            >
                        </label>
                        <label>
                            <span class="wb-label">结束</span>
                            <input
                                class="wb-field"
                                type="time"
                                x-bind:name="`meetings[${index}][ends_at]`"
                                x-model="meeting.ends_at"
                                required
                            >
                        </label>
                    </div>

                    <label class="wb-field-group wb-field-group--full">
                        <span class="wb-label">上课地点</span>
                        <input
                            class="wb-field"
                            type="text"
                            x-bind:name="`meetings[${index}][location]`"
                            x-model="meeting.location"
                            placeholder="例：理教 101"
                        >
                    </label>

                    <label class="wb-field-group">
                        <span class="wb-label">周次模式</span>
                        <select
                            class="wb-select"
                            x-bind:name="`meetings[${index}][week_mode]`"
                            x-model="meeting.week_mode"
                        >
                            <option value="all">每周</option>
                            <option value="odd">单周</option>
                            <option value="even">双周</option>
                            <option value="specific">指定周</option>
                        </select>
                    </label>

                    <div class="wb-field-group grid grid-cols-2 gap-2" x-show="meeting.week_mode !== 'specific'">
                        <label>
                            <span class="wb-label">开始周</span>
                            <input
                                class="wb-field"
                                type="number"
                                min="1"
                                x-bind:max="weekCount"
                                x-bind:name="`meetings[${index}][start_week]`"
                                x-model.number="meeting.start_week"
                            >
                        </label>
                        <label>
                            <span class="wb-label">结束周</span>
                            <input
                                class="wb-field"
                                type="number"
                                min="1"
                                x-bind:max="weekCount"
                                x-bind:name="`meetings[${index}][end_week]`"
                                x-model.number="meeting.end_week"
                            >
                        </label>
                    </div>

                    <label class="wb-field-group" x-show="meeting.week_mode === 'specific'">
                        <span class="wb-label">指定周次</span>
                        <input
                            class="wb-field"
                            type="text"
                            x-bind:name="`meetings[${index}][specific_weeks]`"
                            x-model="meeting.specific_weeks"
                            placeholder="例：1,3,7,11"
                        >
                        <span class="wb-help">使用英文逗号分隔多个周次</span>
                    </label>
                </div>
            </div>
        </section>
    </template>

    <datalist id="meeting-types-{{ $target }}"><option value="Lecture"></option><option value="Tutorial"></option><option value="Lab"></option><option value="Seminar"></option></datalist>

    <button class="wb-btn w-full" type="button" x-on:click="addMeeting('{{ $target }}')">
        <i data-lucide="plus"></i>
        添加时间段
    </button>
</div>

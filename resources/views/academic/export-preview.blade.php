<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>学业任务清单 · {{ $timetable->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="academic-print-page" x-data>
    <nav class="academic-print-toolbar" aria-label="导出操作"><a class="wb-btn" href="{{ route('academic-tasks.export', $timetable) }}"><i data-lucide="chevron-left"></i>重新选择</a><div><a class="wb-btn" href="{{ route('academic-tasks.export-download', ['timetable' => $timetable, ...$options, 'format' => 'csv']) }}"><i data-lucide="download"></i>CSV</a><button class="wb-btn wb-btn--primary" type="button" x-on:click="window.print()"><i data-lucide="file-text"></i>打印 / 另存 PDF</button></div></nav>
    <main class="academic-print-sheet">
        <header class="academic-print-heading"><span class="academic-print-kicker">{{ config('app.name', '课隙') }} · 学业规划</span><h1>学业任务清单</h1><p>{{ $timetable->term_name }}{{ $timetable->term_name ? ' · ' : '' }}{{ $timetable->name }}</p>
            <div class="academic-print-summary"><span>{{ $report['count'] }} 项任务</span><span>{{ $report['completed'] }} 项已完成</span><span>{{ $report['status'] }}</span></div>
            <p class="academic-print-context">{{ $report['scope'] }} · 时区 {{ $report['timezone'] }}<br>导出时间 {{ $report['created'] }} · {{ !empty($options['details']) ? '包含学习计划与项目阶段（各自显示完成状态）' : '仅任务主体' }}</p>
        </header>
        @forelse($report['groups'] as $group)
            <section class="academic-print-group"><h2>{{ $group['course'] }} <small>{{ count($group['tasks']) }} 项</small></h2>
                @foreach($group['tasks'] as $task)
                    <article class="academic-print-task">
                        <div class="academic-print-task-heading"><div><span>{{ $task['type'] }}</span><h3>{{ $task['title'] }}</h3></div><strong class="academic-print-status">{{ $task['status'] }}</strong></div>
                        <dl class="academic-print-times">
                            @if($task['opens'])<div><dt>开放作答 / 提交</dt><dd>{{ $task['opens'] }}</dd></div>@endif
                            @if($task['due'])<div><dt>提交截止</dt><dd>{{ $task['due'] }}</dd></div>@endif
                            @if($task['start'])<div><dt>考试 / 活动时段</dt><dd>{{ $task['start'] }} → {{ $task['end'] ?: '结束待定' }}</dd></div>@endif
                            @if($task['location'])<div><dt>地点</dt><dd>{{ $task['location'] }}</dd></div>@endif
                            @if(!$task['opens'] && !$task['due'] && !$task['start'])<div><dt>任务时间</dt><dd>待安排</dd></div>@elseif($task['opens'] && !$task['due'])<div><dt>提交截止</dt><dd>待定</dd></div>@endif
                        </dl>
                        @if($task['notes'])<div class="academic-print-note"><strong>备注</strong><p>{{ $task['notes'] }}</p></div>@endif
                        @if($task['url'])<div class="academic-print-note"><strong>资料链接</strong><p>{{ $task['url'] }}</p></div>@endif
                        @if($task['entries'])
                            <div class="academic-print-entries"><h4>学习计划与项目阶段</h4>
                                @foreach($task['entries'] as $entry)
                                    <div class="academic-print-entry"><div><strong>{{ $entry['title'] }}</strong><span>{{ $entry['type'] }} · {{ $entry['status'] }}</span></div>
                                        <p>{{ $entry['due'] ? '阶段截止 '.$entry['due'] : '学习时段 '.$entry['start'].' → '.$entry['end'] }}</p>
                                        @if($entry['location'])<p>地点：{{ $entry['location'] }}</p>@endif
                                        @if($entry['notes'])<p class="academic-print-note-text">{{ $entry['notes'] }}</p>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        @empty
            <div class="academic-print-empty"><h2>所选范围暂无任务</h2><p>可以返回调整课程或任务状态，再生成清单。</p></div>
        @endforelse
        <footer class="academic-print-footer">{{ config('app.name', '课隙') }} · {{ $timetable->name }} · 所有时间按 {{ $report['timezone'] }} 显示</footer>
    </main>
</body>
</html>

@props(['timetable', 'title'])
@if($timetable)
    <x-academic-shell :timetable="$timetable" :title="$title" subtitle="属于你的个人安排 · 切换课表方案仍然保留"><x-slot name="actions">{{ $actions ?? '' }}</x-slot>{{ $slot }}</x-academic-shell>
@else
    <x-app-layout><x-slot name="title">{{ $title }}</x-slot><main class="wb-records-main personal-standalone"><div class="academic-heading"><h1>{{ $title }}</h1>{{ $actions ?? '' }}</div>@if(session('status'))<p class="academic-success">{{ session('status') }}</p>@endif @if($errors->any())<div class="academic-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif {{ $slot }}<a class="wb-btn" href="{{ route('dashboard') }}">我的课表</a></main></x-app-layout>
@endif

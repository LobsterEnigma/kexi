@if (session('status'))
    <div class="mb-4 border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
        <p class="font-semibold">操作未完成</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

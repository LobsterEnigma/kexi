<?php

namespace App\Http\Controllers;

use App\Models\CalendarImport;
use App\Models\CalendarSubscription;
use App\Models\Timetable;
use App\Services\CalendarExport;
use App\Services\CalendarImportParser;
use App\Services\CalendarImportWriter;
use App\Services\CanonicalUrl;
use App\Services\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CalendarConnectionController extends Controller
{
    public function index(Request $request, Timetable $timetable, CanonicalUrl $urls)
    {
        $this->authorize('update', $timetable);
        $tab = in_array($request->query('tab'), ['export', 'subscribe'], true) ? $request->query('tab') : 'import';
        $from = $timetable->term_start_date?->toDateString() ?? now($timetable->timezone)->toDateString();
        $to = $timetable->resolvedTermEndDate()?->toDateString() ?? CarbonImmutable::parse($from)->addMonths(4)->toDateString();
        $subscriptions = CalendarSubscription::where('timetable_id', $timetable->id)->whereNull('revoked_at')->latest()->get();
        $links = $subscriptions->mapWithKeys(fn ($subscription) => [$subscription->id => $urls->route('calendar.feed', ['token' => $subscription->token])]);
        $newSubscriptionUrl = $links->get((int) $request->query('created'));

        return view('calendar.index', compact('timetable', 'tab', 'from', 'to', 'subscriptions', 'links', 'newSubscriptionUrl'));
    }

    public function preview(Request $request, Timetable $timetable, CalendarImportParser $parser)
    {
        $this->authorize('update', $timetable);
        $request->validate(['file' => ['required', 'file', 'max:1024', 'extensions:ics'], 'timezone' => ['nullable', 'timezone'], 'fallback_timezone' => ['nullable', 'timezone']]);
        $data = ($request->filled('from') || $request->filled('to')) ? $this->range($request) : [];
        $fallbackFrom = $timetable->term_start_date?->toDateString() ?? now($timetable->timezone)->toDateString();
        $fallbackTo = $timetable->resolvedTermEndDate()?->toDateString() ?? CarbonImmutable::parse($fallbackFrom)->addMonths(4)->toDateString();
        $payload = $parser->upload($request->file('file')->get(), $request->input('fallback_timezone') ?: ($request->user()->timezone ?: $timetable->timezone), $fallbackFrom, $fallbackTo, $data['from'] ?? null, $data['to'] ?? null, $request->input('timezone'));
        CalendarImport::where('expires_at', '<', now())->delete();
        // Keep at most five active previews per user; each preview is encrypted and expires in 30 minutes.
        $old = CalendarImport::where('user_id', $request->user()->id)->latest()->skip(4)->take(100)->pluck('id');
        CalendarImport::whereIn('id', $old)->delete();
        $batch = CalendarImport::create(['user_id' => $request->user()->id, 'timetable_id' => $timetable->id, 'payload' => $payload, 'expires_at' => now()->addMinutes(30)]);

        return redirect()->route('calendar.preview', [$timetable, $batch]);
    }

    public function showPreview(Timetable $timetable, CalendarImport $batch, CalendarImportWriter $writer)
    {
        $this->authorize('update', $timetable);
        $this->checkBatch($timetable, $batch);
        $payload = $batch->payload;
        $duplicates = $writer->duplicates($timetable, $payload['rows']);

        return view('calendar.preview', compact('timetable', 'batch', 'payload', 'duplicates'));
    }

    public function storeImport(Request $request, Timetable $timetable, CalendarImport $batch, CalendarImportWriter $writer)
    {
        $this->authorize('update', $timetable);
        $this->checkBatch($timetable, $batch);
        $data = $request->validate(['selected' => ['required', 'array', 'min:1', 'max:400'], 'selected.*' => ['integer', 'min:0', 'max:399'], 'kinds' => ['required', 'array', 'max:400'], 'kinds.*' => [Rule::in(['personal', 'course', 'event', 'deadline'])]]);
        [$created, $skipped] = $writer->import($timetable, $batch, $data['selected'], $data['kinds']);

        return redirect()->route('calendar.index', $timetable)->with('status', '已导入 '.$created.' 次安排'.($skipped ? '，跳过 '.$skipped.' 个已导入项目' : '').'。可在课表、个人安排或学业任务中查看和编辑。');
    }

    public function download(Request $request, Timetable $timetable, CalendarExport $export)
    {
        $this->authorize('view', $timetable);
        $data = $this->range($request, true);
        if (in_array('courses', $data['contents'], true) && ! $timetable->term_start_date) {
            throw ValidationException::withMessages(['calendar' => '导出课程前请先在课表设置中填写开学日期。']);
        }

        return $this->calendarResponse($export->build($timetable, $data['contents'], $data['from'], $data['to']));
    }

    public function subscribe(Request $request, Timetable $timetable)
    {
        $this->authorize('update', $timetable);
        abort_unless($request->user()->canShare() && app(SiteSettings::class)->bool('sharing_enabled'), 403, '当前站点或账户已停用分享，无法创建订阅。');
        $data = $this->range($request, true);
        $request->validate(['name' => ['required', 'string', 'max:100']]);
        if (CalendarSubscription::where('timetable_id', $timetable->id)->whereNull('revoked_at')->count() >= 20) {
            throw ValidationException::withMessages(['calendar' => '每份课表最多保留 20 条订阅，请先撤销不用的链接。']);
        }
        if (in_array('courses', $data['contents'], true) && ! $timetable->term_start_date) {
            throw ValidationException::withMessages(['calendar' => '订阅课程前请先填写开学日期。']);
        }
        $token = bin2hex(random_bytes(32));
        $subscription = CalendarSubscription::create(['timetable_id' => $timetable->id, 'name' => $request->string('name')->toString(), 'token' => $token, 'token_hash' => hash('sha256', $token), 'contents' => $data['contents'], 'date_from' => $data['from'], 'date_to' => $data['to']]);

        return redirect()->route('calendar.index', [$timetable, 'tab' => 'subscribe', 'created' => $subscription->id])->with('status', '订阅链接已创建，可以在下方复制。');
    }

    public function revoke(Timetable $timetable, CalendarSubscription $subscription)
    {
        $this->authorize('update', $timetable);
        abort_unless($subscription->timetable_id === $timetable->id, 404);
        $subscription->update(['revoked_at' => now(), 'token' => null]);

        return redirect()->route('calendar.index', [$timetable, 'tab' => 'subscribe'])->with('status', '已撤销订阅。外部日历将无法继续获取内容；已有缓存请在对应日历中移除。');
    }

    public function feed(string $token, CalendarExport $export)
    {
        $subscription = CalendarSubscription::where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->with('timetable.user')->firstOrFail();
        abort_unless($subscription->timetable->user->canShare() && app(SiteSettings::class)->bool('sharing_enabled'), 404);

        return $this->calendarResponse($export->build($subscription->timetable, $subscription->contents, $subscription->date_from->toDateString(), $subscription->date_to->toDateString()));
    }

    private function checkBatch(Timetable $timetable, CalendarImport $batch): void
    {
        abort_unless($batch->user_id === auth()->id() && $batch->timetable_id === $timetable->id, 404);
        abort_if($batch->expires_at->isPast(), 410, '预览已过期，请重新上传日历文件。');
    }

    private function range(Request $request, bool $contents = false): array
    {
        $rules = ['from' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before:2100-01-01']];
        if ($contents) {
            $rules += ['contents' => ['required', 'array', 'min:1', 'max:4'], 'contents.*' => ['distinct', Rule::in(array_keys(CalendarExport::CONTENTS))]];
        }
        $data = $request->validate($rules);
        if (CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) > 366) {
            throw ValidationException::withMessages(['to' => '一次最多选择 366 天，请缩短日期范围。']);
        }

        return $data;
    }

    private function calendarResponse(string $body)
    {
        return response($body)->header('Content-Type', 'text/calendar; charset=utf-8')->header('Content-Disposition', 'attachment; filename="kexi-calendar.ics"')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff')->header('Referrer-Policy', 'no-referrer');
    }
}

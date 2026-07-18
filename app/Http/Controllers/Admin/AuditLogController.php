<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = Str::limit(trim((string) $request->query('q')), 100, '');

        $audits = AdminAuditLog::query()
            ->with('actor')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('action', 'like', "%{$search}%")
                    ->orWhereHas('actor', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audits.index', compact('audits', 'search'));
    }
}

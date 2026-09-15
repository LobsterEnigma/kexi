<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAudit;
use App\Services\SiteLogo;
use App\Services\SiteSettings;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SiteLogoController extends Controller
{
    public function update(Request $request, SiteSettings $settings, SiteLogo $logo, AdminAudit $audit, UserAccessService $access): RedirectResponse
    {
        $request->validate([
            'settings_revision' => ['required', 'string', 'size:64'],
            'logo_action' => ['required', 'in:upload,reset'],
            'logo' => ['required_if:logo_action,upload', 'prohibited_if:logo_action,reset', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=4096,max_height=4096'],
        ], [
            'logo.required_if' => '请先选择要上传的 Logo 图片。',
            'logo.image' => '请选择有效的 PNG、JPG 或 WebP 图片。',
            'logo.mimes' => 'Logo 仅支持 PNG、JPG 或 WebP，不支持 SVG。',
            'logo.mimetypes' => '图片内容与支持的格式不符，请重新导出后上传。',
            'logo.max' => 'Logo 不能超过 2 MB。',
            'logo.dimensions' => '图片宽高均需在 32 至 4096 像素之间，推荐 512 × 512 像素。',
        ]);

        Cache::lock('admin:site-settings:update', 15)->block(5, function () use ($request, $settings, $logo, $audit, $access): void {
            $new = null;
            $old = null;
            try {
                DB::transaction(function () use ($request, $settings, $audit, $access, &$new, &$old): void {
                    $access->lockAdminActor($request->user());
                    if (! hash_equals($settings->revision(), $request->string('settings_revision')->toString())) {
                        throw ValidationException::withMessages(['logo' => '设置已更新，请刷新页面后重新选择图片。']);
                    }
                    $old = $settings->get('site_logo');
                    if ($request->input('logo_action') === 'upload') {
                        $file = $request->file('logo');
                        $extension = match ($file->getMimeType()) {
                            'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp',
                        };
                        $new = bin2hex(random_bytes(16)).'.'.$extension;
                        if (! Storage::disk('local')->putFileAs('branding', $file, $new)) {
                            throw ValidationException::withMessages(['logo' => '图片保存失败，请检查 storage 目录写入权限后重试。']);
                        }
                    }
                    $settings->setMany(['site_logo' => $new]);
                    $audit->record($request, 'site.logo_updated', null, ['site_logo' => $old], ['site_logo' => $new]);
                });
            } catch (\Throwable $exception) {
                $logo->delete($new);
                throw $exception;
            }
            $logo->delete($old);
        });

        return back()->with('status', $request->input('logo_action') === 'reset' ? '已恢复默认 Logo。' : '站点 Logo 已更新。');
    }
}

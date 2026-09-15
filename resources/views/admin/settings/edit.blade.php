<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase text-gray-500">管理控制台</p>
            <h1 class="text-xl font-semibold text-gray-900">系统设置</h1>
        </div>
    </x-slot>

    @include('admin.partials.navigation')

    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        @include('admin.partials.feedback')

        <section class="site-logo-panel" aria-labelledby="site-logo-heading"
                 x-data="{ preview: null, filename: '', issue: '', choose(event) { if (this.preview) URL.revokeObjectURL(this.preview); this.preview = null; this.filename = ''; this.issue = ''; const file = event.target.files[0]; if (!file) return; if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2097152) { this.issue = '请选择不超过 2 MB 的 PNG、JPG 或 WebP 图片。'; event.target.value = ''; return; } this.preview = URL.createObjectURL(file); this.filename = file.name; }, destroy() { if (this.preview) URL.revokeObjectURL(this.preview); } }">
            <div class="site-logo-panel__header">
                <div>
                    <h2 id="site-logo-heading">站点 Logo</h2>
                    <p>让登录页、课表和分享页拥有统一的品牌标识。</p>
                </div>
                <span class="site-logo-panel__status" x-text="filename ? '待保存' : @js(config('kexi.site_logo') ? '自定义 Logo' : '默认图标')"></span>
            </div>
            <div class="site-logo-panel__body">
                <div class="site-logo-sample">
                    <span class="site-logo-sample__caption">品牌预览</span>
                    <div class="site-logo-sample__brand">
                        <div class="site-logo-preview" aria-label="Logo 预览">
                            <img x-cloak x-show="preview" :src="preview" alt="待上传的 Logo" x-on:error="if (preview) { issue = '图片无法预览，请选择有效图片。'; preview = null; filename = ''; $refs.logo.value = ''; }">
                            <span x-show="!preview"><x-brand-mark /></span>
                        </div>
                        <strong>{{ config('app.name', '课隙') }}</strong>
                    </div>
                    <p>图片等比展示，保留完整标识</p>
                </div>
                <div class="site-logo-upload">
                    <form id="site-logo-upload-form" method="POST" action="{{ route('admin.settings.logo') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="settings_revision" value="{{ $settingsRevision }}">
                        <input type="hidden" name="logo_action" value="upload">
                        <h3>上传品牌图片</h3>
                        <p id="site-logo-help" class="site-logo-upload__hint">推荐使用 <strong>512 × 512 像素</strong>的方形图片，透明底效果更自然。</p>
                        <div class="site-logo-upload__specs">
                            <span>PNG / WebP / JPG</span>
                            <span>最大 2 MB</span>
                            <span>宽高 32–4096 px</span>
                        </div>
                        <div class="site-logo-upload__picker">
                            <label class="wb-btn cursor-pointer focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2">
                                <span>选择图片</span>
                                <input id="site-logo-file" x-ref="logo" type="file" name="logo" required accept="image/png,image/jpeg,image/webp"
                                       class="sr-only" @change="choose($event)" aria-describedby="site-logo-help">
                            </label>
                            <span class="site-logo-upload__filename" x-text="filename || '选择图片，即可预览效果'"></span>
                        </div>
                        <p x-cloak x-show="issue" x-text="issue" class="mt-2 text-xs text-red-700" role="alert"></p>
                    </form>
                </div>
            </div>
            <div class="site-logo-panel__footer">
                <div class="site-logo-panel__feedback" aria-live="polite">
                    <p x-show="!filename">{{ config('kexi.site_logo') ? '正在使用自定义 Logo' : '正在使用默认图标，可随时更换' }}</p>
                    <p x-cloak x-show="filename" class="text-blue-700">预览尚未保存，点击下方按钮后生效。</p>
                </div>
                <div class="site-logo-panel__actions">
                    @if(config('kexi.site_logo'))
                        <form method="POST" action="{{ route('admin.settings.logo') }}">
                            @csrf
                            <input type="hidden" name="settings_revision" value="{{ $settingsRevision }}">
                            <input type="hidden" name="logo_action" value="reset">
                            <button type="submit" class="wb-btn">恢复默认 Logo</button>
                        </form>
                    @endif
                    <button type="submit" form="site-logo-upload-form" class="wb-btn wb-btn--primary site-logo-panel__save" :disabled="!filename">保存 Logo</button>
                </div>
            </div>
        </section>

        <form
            method="POST"
            action="{{ route('admin.settings.update') }}"
            class="border border-gray-200 bg-white"
            x-data="{ mailer: @js(old('mail_mailer', $settings['mail_mailer'])) }"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="settings_revision" value="{{ $settingsRevision }}">

            <section aria-labelledby="site-settings-heading">
                <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <h2 id="site-settings-heading" class="text-sm font-semibold text-gray-900">站点</h2>
                </div>

                <div class="grid gap-5 px-5 py-5 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">站点名称</span>
                        <input class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                               type="text" name="site_name" maxlength="80" required
                               value="{{ old('site_name', $settings['site_name']) }}">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">站点网址</span>
                        <input class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                               type="url" name="site_url" maxlength="255"
                               value="{{ old('site_url', $siteUrl) }}" placeholder="https://schedule.example.com">
                        <span class="mt-1 block text-xs text-gray-500">用于对外分享链接；后台导航始终使用当前访问域名。</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">显示时区</span>
                        <input class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                               type="text" name="timezone" list="timezone-suggestions" required
                               value="{{ old('timezone', $settings['timezone']) }}" placeholder="Asia/Shanghai">
                        <datalist id="timezone-suggestions">
                            @foreach (['Asia/Shanghai', 'Asia/Hong_Kong', 'Asia/Taipei', 'Asia/Singapore', 'Asia/Tokyo', 'Asia/Seoul', 'UTC', 'Europe/London', 'America/Toronto', 'America/New_York', 'America/Los_Angeles', 'Australia/Sydney'] as $timezone)
                                <option value="{{ $timezone }}"></option>
                            @endforeach
                        </datalist>
                        <span class="mt-1 block text-xs text-gray-500">日期时间按此时区显示，数据库统一使用 UTC。</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">登录会话时长</span>
                        <span class="mt-1 flex items-center gap-2">
                            <input class="block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                                   type="number" name="session_lifetime_minutes" min="15" max="10080" required
                                   value="{{ old('session_lifetime_minutes', $settings['session_lifetime_minutes']) }}">
                            <span class="shrink-0 text-sm text-gray-500">分钟</span>
                        </span>
                        <span class="mt-1 block text-xs text-gray-500">新时长从后续请求开始生效，不会立即退出当前用户。</span>
                    </label>
                </div>
            </section>

            <section class="border-t border-gray-200" aria-labelledby="access-settings-heading">
                <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <h2 id="access-settings-heading" class="text-sm font-semibold text-gray-900">访问控制</h2>
                </div>

                <div class="divide-y divide-gray-200">
                    <label class="flex items-start justify-between gap-6 px-5 py-5">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">允许公开注册</span>
                            <span class="mt-1 block text-sm text-gray-500">关闭后拒绝新的注册请求。</span>
                        </span>
                        <input type="hidden" name="registration_enabled" value="0">
                        <input class="mt-1 h-5 w-5 border-gray-300 text-gray-900 focus:ring-gray-500"
                               type="checkbox" name="registration_enabled" value="1"
                               @checked(old('registration_enabled', $settings['registration_enabled']))>
                    </label>

                    <label class="flex items-start justify-between gap-6 px-5 py-5">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">允许分享课表</span>
                            <span class="mt-1 block text-sm text-gray-500">关闭后立即暂停全部公开分享链接。</span>
                        </span>
                        <input type="hidden" name="sharing_enabled" value="0">
                        <input class="mt-1 h-5 w-5 border-gray-300 text-gray-900 focus:ring-gray-500"
                               type="checkbox" name="sharing_enabled" value="1"
                               @checked(old('sharing_enabled', $settings['sharing_enabled']))>
                    </label>
                </div>
            </section>

            <section class="border-t border-gray-200" aria-labelledby="mail-settings-heading">
                <div class="border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <h2 id="mail-settings-heading" class="text-sm font-semibold text-gray-900">邮件</h2>
                </div>

                <div class="grid gap-5 px-5 py-5 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-gray-700">发送方式</span>
                        <select class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500 sm:max-w-sm"
                                name="mail_mailer" x-model="mailer" required>
                            <option value="log">仅写入日志</option>
                            <option value="smtp">SMTP</option>
                        </select>
                    </label>

                    <div class="contents" x-cloak x-show="mailer === 'smtp'">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">SMTP 主机</span>
                            <input class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                                   type="text" name="mail_host" maxlength="255"
                                   value="{{ old('mail_host', $settings['mail_host']) }}" placeholder="smtp.example.com">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">SMTP 端口</span>
                            <input class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                                   type="number" name="mail_port" min="1" max="65535"
                                   value="{{ old('mail_port', $settings['mail_port']) }}">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">连接安全</span>
                            <select class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                                    name="mail_scheme">
                                <option value="" @selected(old('mail_scheme', $settings['mail_scheme']) === null)>自动</option>
                                <option value="smtp" @selected(old('mail_scheme', $settings['mail_scheme']) === 'smtp')>SMTP / STARTTLS</option>
                                <option value="smtps" @selected(old('mail_scheme', $settings['mail_scheme']) === 'smtps')>SMTPS</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">SMTP 用户名</span>
                            <input class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                                   type="text" name="mail_username" maxlength="255" autocomplete="username"
                                   value="{{ old('mail_username', $settings['mail_username']) }}">
                        </label>

                        <div class="block sm:col-span-2">
                            <label for="mail-password" class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                SMTP 密码
                                @if ($mailPasswordConfigured)
                                    <span class="border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-normal text-emerald-700">已配置</span>
                                @elseif ($mailPasswordInvalid)
                                    <span class="border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-normal text-red-700">无法解密</span>
                                @endif
                            </label>
                            <input class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500 sm:max-w-lg"
                                   id="mail-password" type="password" name="mail_password" maxlength="2048" autocomplete="new-password"
                                   placeholder="{{ $mailPasswordConfigured ? '留空保持现有密码' : ($mailPasswordInvalid ? '重新输入 SMTP 密码' : '输入 SMTP 密码') }}">
                            @if ($mailPasswordInvalid)
                                <p class="mt-1 text-xs text-red-700">当前 APP_KEY 无法解密已保存的密码，请重新输入或清除。</p>
                            @endif
                            <input type="hidden" name="clear_mail_password" value="0">
                            @if ($mailPasswordConfigured || $mailPasswordInvalid)
                                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                                    <input class="border-gray-300 text-gray-900 focus:ring-gray-500" type="checkbox" name="clear_mail_password" value="1">
                                    清除已保存的密码
                                </label>
                            @endif
                        </div>
                    </div>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">发件地址</span>
                        <input class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                               type="email" name="mail_from_address" maxlength="255" required
                               value="{{ old('mail_from_address', $settings['mail_from_address']) }}">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">发件名称</span>
                        <input class="mt-1 block w-full border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                               type="text" name="mail_from_name" maxlength="80" required
                               value="{{ old('mail_from_name', $settings['mail_from_name']) }}">
                    </label>
                </div>
            </section>

            <div class="flex justify-end border-t border-gray-200 bg-gray-50 px-5 py-4">
                <button type="submit" class="inline-flex items-center gap-2 bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    <i data-lucide="check" class="h-4 w-4"></i>
                    保存设置
                </button>
            </div>
        </form>
    </div>
</x-app-layout>

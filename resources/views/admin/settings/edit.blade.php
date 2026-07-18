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

# 简化部署指南

课隙采用“最小启动配置 + 管理后台设置”。数据库连接之前无法读取后台数据，因此 `APP_KEY` 和数据库连接仍属于启动配置；站点、会话、邮件、注册和分享均在 `/console/settings` 管理。

## 三步安装

### 1. 配置站点目录

- PHP 8.2+，启用 `bcmath`、`ctype`、`curl`、`dom`、`fileinfo`、`mbstring`、`openssl`、`pdo`、`tokenizer`、`xml`、`xmlreader`、`xmlwriter`
- 快速安装启用 `pdo_sqlite`；MySQL 安装改为启用 `pdo_mysql`
- 从源码构建前端时使用 Node.js 18、20 或 22+
- Web 根目录必须指向项目的 `public/`
- 生产站点必须启用 HTTPS；生产环境默认使用 Secure Session Cookie
- 使用与 PHP-FPM 同组的站点部署用户运行安装器。源码归部署用户所有并只给 PHP-FPM 读取权限；仅在创建 `.env` 时允许站点组写项目根目录，长期可写范围限制为 `storage/`、`bootstrap/cache/` 和使用 SQLite 时的 `database/`

### 2. 安装依赖与前端

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

如果上传的发布包已经包含 `vendor/` 和 `public/build/`，可以跳过 Composer 与 Node 构建。不要把整套源码所有权交给 PHP-FPM 用户。

### 3. 运行安装器

```bash
php artisan kexi:install admin@example.com
```

安装器会自动生成 `APP_KEY`、创建 SQLite 文件、执行迁移、初始化系统设置、引导创建首位管理员，并在生产环境生成优化缓存。命令可重复执行，不会清空数据或轮换已有密钥。

登录后打开 `/console/settings`，配置：

- 站点名称、正式网址、显示时区、登录会话时长
- 是否开放注册、是否允许分享
- 邮件日志或 SMTP、发件人及 SMTP 凭据

SMTP 密码使用 `APP_KEY` 加密保存，不会回显，也不会写入管理审计。

## `.env` 只剩什么

默认 `.env.example` 只有四行：

```dotenv
APP_ENV=production
APP_KEY=
APP_DEBUG=false
DB_CONNECTION=sqlite
```

`APP_KEY` 由安装器填写。使用 SQLite 时无需手工修改 `.env`。备份或迁移站点时必须让数据库与原 `.env` 中的 `APP_KEY` 配套恢复；密钥不匹配时后台会提示 SMTP 密码无法解密，可重新输入或清除。

以下内容不再要求写入 `.env`：`APP_NAME`、`APP_URL`、时区、Session、Cache、Queue、Redis、Memcached、AWS、SMTP、发件人和管理端路径。

## 改用 MySQL

公开多用户站点建议使用 MySQL。把 `.env` 中的 `DB_CONNECTION=sqlite` 替换为：

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kexi
DB_USERNAME=kexi
DB_PASSWORD=数据库密码
```

数据库连接必须在应用启动前可用，因此这部分不能放进管理后台。其余设置仍全部在后台完成。

SQLite 适合单机、小规模站点，不适用于多实例、共享网络磁盘或高并发写入。项目已启用 WAL 和忙等待；涉及大量并发管理操作时仍应使用 MySQL 的行锁能力。

## Nginx 示例

```nginx
server {
    listen 443 ssl http2;
    server_name schedule.example.com;
    root /www/wwwroot/kexi/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

不要把站点根目录指向项目根目录，否则 `.env`、源代码和数据库文件可能暴露。

## 更新

先备份网站、数据库和 `.env`。SQLite 使用在线备份或 `VACUUM INTO`，不要在 WAL 活跃时只复制主数据库文件。保留原 `APP_KEY`，不要重新生成密钥。

在包含 `artisan` 的网站根目录执行：

```bash
php artisan down
```

将服务器包解压覆盖到该根目录，包括包内的 `vendor/` 和 `public/build/`。保留 `.env`、数据库、用户上传文件及 `storage/` 运行数据，不要先删除网站目录。当前服务器包已带生产依赖和前端构建，无需在服务器运行 Composer/npm。

如果使用 GitHub 源码更新，更新源码后先执行 `composer install --no-dev --prefer-dist --optimize-autoloader` 和 `npm ci && npm run build`。

随后逐条执行，任何一步报错先处理再继续：

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan up
```

`migrate --force` 会补齐遗漏的迁移；不要执行 `migrate:fresh` 或 `db:wipe`。更新后强制刷新浏览器，在实际站点验证登录和主要功能。

GitHub 副本更新：解压干净源码包覆盖仓库，保留 `.git`，在 GitHub Desktop 查看变更后提交并推送。旧版本留下的 `docs/updates-*.md` 需在副本中删除一次，因为覆盖解压不会自动删除旧文件。后续功能和修复仅更新根目录 `development.md`。

## 用户与课表时区

注册时默认识别当前设备时区，用户可直接选择其他时区。已有用户升级后首次打开认证页面时初始化个人默认值；只填充空值，不覆盖已有选择，也不修改历史课表。浏览器检测失败时请在「账户设置 → 时区与课表」手动选择，不会使用后台站点时区代替。服务端时间与数据库仍保持 UTC。

账户默认时区用于新建课表与个人安排；每张课表有独立时区，侧栏左下角直接显示，点击可修改。账户页也列出各课表的「调整」入口。纠正之前误用的 `Asia/Shanghai` 时，课程保留原有星期与上课钟点；已经按 UTC 保存的任务和个人安排保留真实时间、换算显示。若任务最初按错误时区录入，需要检查其具体时间，不会擅自批量移动。

用户手动选择会保存在账户，换设备或旅行不自动覆盖；需要改用当前设备时，点击「使用设备时区」并保存。管理后台时区只负责后台审计等管理时间展示。此次更新新增 `users.timezone`，请执行 `php artisan migrate --force`。

## 外观主题

在工作台左下角账户上方选择「浅色、深色、跟随系统」；手机打开左侧导航后可切换。登录页、账户设置、后台和分享页也有切换入口。首次默认跟随系统，选择保存在当前浏览器，同源标签页同步；更换设备或浏览器时分别设置，无需后台配置或新增环境变量。

课程原有配色在深色下自动调整显示，不修改保存的颜色。导出图片仍使用导出窗口中选择的主题，任务打印/PDF 保持白底。

深色采用墨黑画布、分层表面与清透蓝操作，选中态使用细线和小面积颜色，减少灰紫色块；滚动条、表单、弹窗、账户与后台沿用同一套颜色。

## 日历导入、导出与订阅

侧栏「日历连接」提供三个入口，无需新增环境变量、队列或定时任务。本次更新新增数据库迁移及 `sabre/vobject` 依赖，必须执行 `php artisan migrate --force`；源码更新还需重新安装 Composer 依赖与构建前端。服务器包已包含生产依赖和构建结果，PHP 仍需启用上述 XML 扩展。

- **导入文件**：选择或拖入 UTF-8 `.ics` 后直接预览，默认自动识别日期范围，接受 2000–2099 年内安排。最大 1 MB、500 个源事件，预览最多展开 400 条、范围最多 366 天。支持常见日/周/月/年重复、排除日期、单次改期、全天活动和有截止日期的非重复 VTODO。无结束日期的重复规则按当前学期展开并在预览说明；超出数量或跨度限制会要求在「更多选项」缩小范围，不会静默截断。手动日期范围需要同时填写开始与结束。
- **自动识别时区**：事件自身的 TZID 或 UTC 标记优先；未单独标注时区的时间优先使用文件的 `X-WR-TIMEZONE`，否则使用用户保存的默认时区，未设置时检测设备，再回退课表时区。采用兜底时会在预览说明；更多选项可手动指定无时区时间的解释方式，但不会覆盖事件已声明的时区。预览统一换算为课表时区，全天活动保留原始日历日期。
- **预览归类**：可批量或逐条设为课程、学业活动、截止事项、个人安排。课程需在当前学期内、同日且结束晚于开始；重复课程合并为指定教学周时间段。其他重复活动展开为独立记录，可分别编辑。导入的是所选范围的快照，不会自动连接原日历；同一记录重复导入跳过，已删除记录可重新导入。预览 30 分钟后过期。
- **导出文件**：下载当前选择范围和内容的快照，之后修改不会更新已下载文件。没有任何时间的任务不生成日历事件。任务的开放、截止与考试时间分别明确标记；把导入项目归为截止事项时使用该项目的开始时间，全天项目使用最后一天 23:59。
- **订阅日历**：为当前课表生成链接，范围在创建时固定，最多 366 天；每次客户端读取时重新计算范围内的安排。先在后台配置正确的正式 HTTPS 站点网址，再把链接添加到 Apple 日历的「新建日历订阅」、Google 日历网页版的「通过网址添加」，或 Outlook 的「从 Web 订阅」。实际菜单随客户端版本变化；外部客户端自行决定刷新频率，可能有数小时延迟。订阅是单向读取，在外部修改不会写回课隙；本版不提供外部 URL 自动导入。

默认仅选择课程，个人安排必须明确勾选；备注、资料链接和请假原因始终排除。订阅是免登录的持链接访问，任何拿到链接的人均可读取所选内容。所有者可重新查看、复制或撤销链接，每张课表最多 20 条有效订阅。全局/用户禁止分享、账户审查或封禁时订阅不可访问；撤销阻止后续读取，但无法删除外部客户端已经缓存的数据。

订阅令牌使用 `APP_KEY` 加密保存以便所有者重新复制，另存摘要用于查找；备份数据库时务必保留配套密钥。生产访问日志应对 `/calendar/feed/{token}.ics` 路径脱敏，与 `/s/{token}` 一样视为敏感凭证。

## 自定义站点 Logo

管理员打开「系统设置 → 站点 Logo」，选择图片，查看预览后点击「保存 Logo」。推荐 **512 × 512 像素透明底 PNG 或 WebP**，也支持 JPG；最大 2 MB，宽高均在 32–4096 像素内。不支持 SVG，非方形图片会等比缩放、不裁切。

登录/注册、课表侧栏、账户页、管理后台和公开分享页共用此图片。点击「恢复默认 Logo」可换回课隙图标。Logo 单独保存，其他站点字段使用下方「保存设置」。

文件保存在 `storage/app/private/branding/`，配置保存在数据库。备份、迁移和覆盖更新时保留该目录及数据库；目录需由 PHP 进程可写。无需新增环境变量、GD 扩展或运行 `storage:link`。缺失文件会自动显示默认图标。

## Turnstile 与 Passkey

两项功能默认关闭，在后台「登录安全」配置，无需新增环境变量。

1. 先在「系统设置」保存正式 HTTPS 站点网址，例如 `https://kexi.example.com`。
2. Turnstile：在 Cloudflare 创建 Managed（托管式）组件，将正式域名加入允许列表，填写 Site Key 和 Secret Key，选择应用于登录、注册或两者，然后开启保存。登录验证覆盖密码和 Passkey 入口。
3. Passkey：填写可选的设备显示名称，开启保存。无需 API Key，绑定域名来自站点网址。

Secret 加密保存，留空保持原值，不回显；关闭 Turnstile 后可选择清除密钥。服务器会核对令牌、场景和域名，网络失败、令牌过期或不匹配时不放行。先保留管理员登录窗口，再在另一个窗口测试真实注册和登录。

用户在「账户设置 → 通行密钥」填写名称和当前密码，按设备提示使用指纹、面容、PIN 或支持的安全密钥添加。每个账户最多 20 个；服务器仅保存公钥，不保存生物识别信息。可查看最近使用时间、重命名和删除，管理操作验证当前密码。

密码登录和找回密码继续可用。关闭 Passkey 阻止添加和登录，保留已有密钥供管理；重新开启可继续使用。设备需要支持可发现凭据及用户验证，域名改变后需重新添加。生产必须 HTTPS，开发仅允许 `http://localhost`。封禁与审查仍生效。

若错误配置导致无法登录，在服务器项目根目录执行：

```bash
php artisan kexi:auth-disable turnstile
```

仅关闭 Passkey 用 `php artisan kexi:auth-disable passkeys`；同时关闭用 `php artisan kexi:auth-disable all`。命令保留配置与已有密钥，恢复密码登录后回后台修正。实现使用 OpenSSL 和 Composer 锁定的 `lbuchs/webauthn`。

参考：[Cloudflare 服务端验证](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)、[lbuchs/WebAuthn](https://github.com/lbuchs/WebAuthn)。

分享 token 是访问凭证。生产访问日志应避免记录完整 `/s/{token}` 路径，或对该路径脱敏。公开分享响应已设置 `no-store`、`no-referrer` 和 `noindex`。

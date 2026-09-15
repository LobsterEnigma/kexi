# 简化部署指南

课隙采用“最小启动配置 + 管理后台设置”。数据库连接之前无法读取后台数据，因此 `APP_KEY` 和数据库连接仍属于启动配置；站点、会话、邮件、注册和分享均在 `/console/settings` 管理。

## 三步安装

### 1. 配置站点目录

- PHP 8.2+，启用 `bcmath`、`ctype`、`curl`、`dom`、`fileinfo`、`mbstring`、`openssl`、`pdo`、`tokenizer`、`xml`
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

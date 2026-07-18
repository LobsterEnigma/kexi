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

```bash
php artisan down --render="errors::503"
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

更新前备份数据库和 `.env`。SQLite 使用在线备份或 `VACUUM INTO`，不要在 WAL 活跃时只复制主数据库文件。

分享 token 是访问凭证。生产访问日志应避免记录完整 `/s/{token}` 路径，或对该路径脱敏。公开分享响应已设置 `no-store`、`no-referrer` 和 `noindex`。

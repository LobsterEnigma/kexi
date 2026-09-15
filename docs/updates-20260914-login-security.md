# Turnstile 与 Passkey 登录安全

本版新增后台「登录安全」，两项功能默认关闭，不需要新增环境变量。

## 管理员配置

1. 在「系统设置」保存正式的 HTTPS 站点网址，例如 `https://kexi.example.com`。
2. 打开「登录安全」。
3. Turnstile：在 Cloudflare 创建 Managed（托管式）组件，把正式域名加入允许列表，粘贴 Site Key 和 Secret Key，选择应用于登录、注册或两者，开启后保存。登录验证同时覆盖密码与通行密钥入口。
4. Passkey：填写可选的设备显示名称，开启后保存。无需额外 API Key，绑定域名自动取自站点网址。

Secret Key 加密保存在数据库，留空保留原值，不回显、不进入表单旧输入或审计明文。关闭 Turnstile 后可选择清除密钥。后台设置有并发版本检查，只有正常管理员经密码确认后可修改。

Turnstile 在服务器调用 Cloudflare 验证，核对成功状态、场景与域名。网络失败、缺少令牌、过期令牌或不匹配都不会放行。组件加载失败时提供重试入口。

## 用户使用 Passkey

在「账户设置 → 通行密钥」填写名称和当前登录密码，点击添加，按设备提示使用指纹、面容、PIN 或支持的安全密钥确认。

下次在登录页选择「使用通行密钥登录」，不需要先填写邮箱和密码。启用登录人机验证时先完成验证。账户设置可查看最近使用时间、重命名或删除密钥；管理操作需要当前密码，删除后立即不能再用该密钥登录。

- 密钥属于个人账户，可添加多个（最多 20 个）；服务器只保存公钥，不保存生物识别数据或私钥。
- 密码登录与找回密码继续可用；密码重置会使旧会话和待完成的密钥添加失效。
- 关闭 Passkey 会阻止添加和登录，保留已有密钥并允许用户管理；重新开启可继续使用。
- 设备须支持可发现凭据和用户验证。浏览器/设备不支持或用户取消时可使用密码登录。
- 域名绑定是 Passkey 标准的要求，换域名需要重新添加；子域名和不同端口不能冒用原站点。生产环境使用 HTTPS；仅开发环境允许 `http://localhost`。
- 封禁、审查、会话版本、CSRF、操作频率限制继续生效。挑战绑定会话和用途，3 分钟过期且只能消费一次，服务端验证签名、用户句柄、用户在场与解锁确认、计数器。

## 更新包

- `kexi-login-security-20260914.zip`：完整干净源码，用于自行更新 GitHub，不包含依赖、测试、`.env`、数据库或工具目录。
- `kexi-server-login-security-20260914.zip`：服务器完整覆盖更新包，包含 `public/build` 和按 `composer.lock` 安装的生产 `vendor`，已包含新增 WebAuthn 库；本包不需要在服务器执行 npm 或 Composer。依赖的许可证保留，WebAuthn 演示目录不打包。

## 更新已有服务器

先备份网站、数据库和 `.env`，在有 `artisan` 的网站根目录执行：

```bash
php artisan down
```

上传并解压**本次服务器包**，覆盖源码、`public/build` 和 `vendor`。保留 `.env`、数据库、用户上传文件及 `storage` 的现有运行数据，不删除整个网站目录。然后逐条执行：

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan up
```

需要 PHP 8.2+ 以及原有扩展、OpenSSL 扩展。本版有数据库迁移，不能省略 `migrate --force`，不要执行 `migrate:fresh`。

如果使用 GitHub 源码更新而非服务器包，覆盖后先运行 `composer install --no-dev --prefer-dist --optimize-autoloader`，并执行 `npm ci && npm run build`，再清缓存、迁移与优化。

更新后两项功能保持默认关闭；请管理员配置后启用。先保留当前管理员登录窗口，再在另一窗口验证实际登录和注册。

## 配错后的恢复

若人机验证配置错误导致无法登录，有服务器访问权限时，在网站根目录执行：

```bash
php artisan kexi:auth-disable turnstile
```

仅关闭 Passkey 用 `php artisan kexi:auth-disable passkeys`；同时关闭用 `php artisan kexi:auth-disable all`。这些命令保留配置和已添加密钥，恢复密码登录后可回后台修正。

## 验证与边界

本地 PHP 8.3、SQLite、Chromium / Playwright（本环境未提供 Browser 插件），在独立临时数据库与 `http://localhost:8013` 验证。桌面 1440px，手机 390px/320px。

- 实际 WebAuthn 虚拟认证器完成添加、重命名、无密码登录、删除和删除后拒绝。
- 检查错误签名、Origin、用户句柄、重放、过期、跨会话、跨账户、关闭功能、封禁及审查。
- Turnstile 上游模拟覆盖成功、缺失/畸形令牌、域名/场景不匹配、过期/重复、网络故障；HTTP 检查注册、密码登录和 Passkey 登录选项均不可绕过。
- 后台配置完整性、旧版本冲突、Secret 留空保留及不回显、移动端布局、组件重试与加载失败。
- PHP 语法、Blade 编译、Pint、Composer 锁文件和 Vite 构建检查。

Cloudflare 真实账号/域名密钥、实体生物识别设备、Safari/Firefox 和生产 MySQL/MariaDB 尚未现场验证；部署配置后需在实际域名走一次注册、密码登录和 Passkey 登录。

实现参考：[Cloudflare 服务端验证](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)、[lbuchs/WebAuthn](https://github.com/lbuchs/WebAuthn)。

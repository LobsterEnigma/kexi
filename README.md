# 课隙

课隙是一个面向大学生的多用户课表可视化工具。它把教学周、单双周和指定周次转换成可读的周视图与月视图，并明确标出时间重叠、课程临近和不同程度的宽松间隔。

## 已实现功能

- 邮箱注册、登录、密码重置和账户管理
- 每位用户管理多张课表，每门课程支持多个时间段
- 学期范围既可填写总周数，也可填写学期截止日期，两者自动换算
- `all / odd / even / specific` 四种教学周规则
- 红色重叠、黄色临近、三档绿色宽松度，以及分钟级文字说明
- 单次或多周临时停课、整门课程临时隐藏，并可随时恢复
- 周课表与月课表均可导出三套主题的高清 PNG 图片
- 随机分享链接、可选密码和过期时间、随时撤销
- 管理端用户审查与封禁、处置原因通知、注册开关、全局/用户分享治理和审计记录
- 周/月课表自由切换、桌面三栏工作台、平板抽屉和移动端可滚动课表

## 技术栈

- PHP 8.2+ / Laravel 12
- SQLite 3（快速部署）或 MySQL 8+ / MariaDB 10.6+
- Blade、Alpine.js、Tailwind CSS、Lucide Icons、Vite

## 本地开发

```bash
composer install
npm install
cp .env.example .env
# 将 .env 中的 APP_ENV 改为 local
php artisan key:generate
php -d extension=pdo_sqlite artisan migrate:fresh
php -d extension=pdo_sqlite artisan db:seed --class=DemoSeeder
npm run build
php -d extension=pdo_sqlite artisan serve
```

如果本机 PHP 已在 `php.ini` 启用 `pdo_sqlite`，命令中的 `-d extension=pdo_sqlite` 可以省略。应用默认运行在 `http://127.0.0.1:8000`。

`DemoSeeder` 仅用于本地体验：学生账户 `demo@kexi.test`，管理员账户 `admin@kexi.test`，两者密码均为 `password`。生产环境不要运行该 seeder。

## 使用干净部署包

干净部署包已经包含生产依赖和构建后的前端资源，服务器不需要安装 Node.js，也不需要执行 `npm install` 或 `composer install`。

1. 上传并解压全部文件，将 Nginx/Apache 站点根目录指向项目的 `public/`。
2. 确保 PHP 可以写入 `storage/`、`bootstrap/cache/` 和 `database/`。
3. 在项目目录执行一次：

```bash
php artisan kexi:install admin@example.com
```

安装器会创建最小 `.env`、生成密钥、建立 SQLite 数据库并创建管理员。随后登录 `/console/settings`，即可在管理后台填写域名、时区、邮件、注册和分享设置。

## 从源码生产安装

1. 将 Nginx/Apache 站点根目录指向项目的 `public/`。
2. 执行：

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan kexi:install admin@example.com
```

安装器会自动创建最小 `.env`，默认使用 SQLite，不需要手工填写数据库配置。公开多用户站点可在安装前创建 `.env` 并填写 MySQL 连接。登录 `/console/settings` 后即可配置站点名称、网址、时区、会话、SMTP、注册和分享；这些设置不再要求写入 `.env`。

完整的最小配置、MySQL 选项、Nginx、目录权限和更新说明见 [部署文档](docs/deployment.md)。领域规则和安全边界见 [架构文档](docs/architecture.md)。

## 时间判定

- 时间段使用半开区间 `[start, end)`；`09:00-10:00` 与 `10:00-11:00` 不重叠，但间隔为 0，会标黄。
- 同一天、同一教学周才会比较；地点只展示，不参与首版判定。
- 每张课表可选择 15、30、45 或 60 分钟的临近阈值。
- 红色优先于黄色；即使课程同时涉及重叠和临近，问题列表仍保留全部关系。
- 分析结果不持久化，修改时间或阈值后会立即重新计算。

视觉实现以 [工作台参考图](docs/design/timetable-workspace-reference.png) 为基准，界面控件和课表内容均为可访问、可交互的原生页面元素。

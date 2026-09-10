# 侧栏品牌位置微调

桌面左上角的 Logo 和「课隙」整体上移 12px，保留顶部留白，下面导航与课表方案位置不变。课表页和学业任务页共用此调整；手机导航抽屉维持原间距。

本次仅调整样式，没有新增环境变量、依赖或数据库迁移。

## 完整更新包

- `kexi-brand-spacing-20260910.zip`：干净源码，供覆盖 GitHub 本地仓库后自行提交，保留 `.git`。
- `kexi-server-brand-spacing-20260910.zip`：已有服务器覆盖更新包，包含构建好的前端资源。

两个包均包含此前的功能和修复，排除 `.env`、数据库、测试文件和本地依赖。

## 更新服务器

先备份网站、数据库和 `.env`，在包含 `artisan` 的网站根目录执行 `php artisan down`。上传服务器包并解压覆盖，保留现有 `.env`、数据库、`vendor/` 和 `storage/`，然后逐条执行：

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan up
```

出现错误时先处理再继续。无需运行 npm，不要执行 `migrate:fresh`。更新后强制刷新浏览器。

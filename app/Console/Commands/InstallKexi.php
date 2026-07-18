<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;

class InstallKexi extends Command
{
    protected $signature = 'kexi:install
        {email? : 首位管理员邮箱}
        {--name= : 首位管理员名称}
        {--no-admin : 只初始化应用，不创建管理员}';

    protected $description = '初始化数据库、系统设置和首位管理员';

    public function handle(): int
    {
        $this->components->info('正在初始化课隙');

        if (! File::exists($this->laravel->environmentFilePath())) {
            File::copy(base_path('.env.example'), $this->laravel->environmentFilePath());
            $this->components->info('已创建最小 .env 文件。');
        }

        if ($this->laravel->configurationIsCached()) {
            $this->call('optimize:clear');
            $this->components->error('当前进程已载入旧配置，未继续安装。请通过 php artisan kexi:install 重新运行。');

            return self::FAILURE;
        }

        if (blank(config('app.key'))) {
            $result = $this->call('key:generate', ['--force' => true]);

            if ($result !== self::SUCCESS) {
                return $result;
            }
        }

        if (! $this->ensureDatabaseIsReady()) {
            return self::FAILURE;
        }

        $result = $this->call('migrate', ['--force' => true]);

        if ($result !== self::SUCCESS) {
            return $result;
        }

        $result = $this->call('db:seed', [
            '--class' => SiteSettingSeeder::class,
            '--force' => true,
        ]);

        if ($result !== self::SUCCESS) {
            return $result;
        }

        if (! $this->option('no-admin') && ! User::query()->where('is_admin', true)->exists()) {
            $arguments = array_filter([
                'email' => $this->argument('email'),
                '--name' => $this->option('name'),
            ], fn (mixed $value): bool => filled($value));

            $result = $this->call('admin:create', $arguments);

            if ($result !== self::SUCCESS) {
                return $result;
            }
        } elseif (! $this->option('no-admin')) {
            $this->components->info('已存在管理员，跳过管理员创建。');
        }

        if ($this->laravel->environment('production')) {
            $result = $this->call('optimize');

            if ($result !== self::SUCCESS) {
                return $result;
            }
        }

        $this->newLine();
        $this->components->info('安装完成。登录 /console/settings 配置站点与邮件。');

        return self::SUCCESS;
    }

    private function ensureDatabaseIsReady(): bool
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver", $connection);
        $pdoDriver = $driver === 'mariadb' ? 'mysql' : $driver;

        if (! in_array($pdoDriver, PDO::getAvailableDrivers(), true)) {
            $this->components->error("未启用 pdo_{$pdoDriver}。请启用对应 PHP 扩展后重试。");

            return false;
        }

        if ($driver !== 'sqlite') {
            return true;
        }

        $database = (string) config("database.connections.{$connection}.database");

        if ($database === '' || $database === ':memory:') {
            return true;
        }

        $isAbsolute = str_starts_with($database, '/')
            || str_starts_with($database, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $database) === 1;
        $path = $isAbsolute ? $database : base_path($database);

        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            File::put($path, '');
            $this->components->info("已创建 SQLite 数据库：{$path}");
        }

        return true;
    }
}

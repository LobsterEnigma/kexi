<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email? : 管理员邮箱} {--name= : 管理员名称}';

    protected $description = '安全地创建一名系统管理员';

    public function handle(): int
    {
        $email = Str::lower(trim((string) ($this->argument('email') ?: $this->ask('管理员邮箱'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('管理员名称')));
        $password = (string) $this->secret('密码（输入不会显示）');
        $confirmation = (string) $this->secret('再次输入密码');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->forceFill([
                'is_admin' => true,
                'email_verified_at' => now(),
            ])->save();

            AdminAuditLog::query()->create([
                'actor_id' => null,
                'action' => 'user.admin_created',
                'target_type' => $user->getMorphClass(),
                'target_id' => $user->id,
                'before' => null,
                'after' => [
                    'email' => $user->email,
                    'is_admin' => true,
                ],
                'ip_address' => null,
                'user_agent' => 'artisan admin:create',
            ]);

            return $user;
        });

        $this->info("管理员 {$user->email} 已创建。");

        return self::SUCCESS;
    }
}

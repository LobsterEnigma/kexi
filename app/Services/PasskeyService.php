<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use lbuchs\WebAuthn\WebAuthn;

class PasskeyService
{
    public function __construct(private readonly LoginSecurity $security) {}

    public function config(Request $request): array
    {
        $config = $this->security->passkeyConfig();
        $origin = $request->header('Origin');
        if ($origin !== $config['origin']) {
            $this->fail('请从后台配置的站点网址使用通行密钥。');
        }

        return $config;
    }

    public function server(array $config): WebAuthn
    {
        return new WebAuthn($config['name'], $config['rpId'], ['none'], true);
    }

    public function issue(Request $request, string $purpose, array $config, WebAuthn $server, array $extra = []): string
    {
        $id = bin2hex(random_bytes(32));
        $session = hash('sha256', $request->session()->getId());
        DB::transaction(function () use ($request, $purpose, $config, $server, $extra, $id, $session) {
            DB::table('passkey_challenges')->where('expires_at', '<', now())->delete();
            DB::table('passkey_challenges')->where('session_hash', $session)->where('purpose', $purpose)->delete();
            DB::table('passkey_challenges')->insert([
                'id' => $id, 'session_hash' => $session, 'user_id' => $request->user()?->id, 'purpose' => $purpose, 'expires_at' => now()->addMinutes(3),
                'payload' => json_encode(['challenge' => self::encode($server->getChallenge()->getBinaryString()), 'revision' => $config['revision'],
                    'auth_version' => $request->user()?->auth_version, ...$extra], JSON_THROW_ON_ERROR),
            ]);
        });

        return $id;
    }

    public function consume(Request $request, string $purpose, array $config): array
    {
        $row = DB::transaction(function () use ($request, $purpose) {
            $row = DB::table('passkey_challenges')->where('id', $request->input('challenge_id'))
                ->where('session_hash', hash('sha256', $request->session()->getId()))->where('purpose', $purpose)->lockForUpdate()->first();
            if ($row) {
                DB::table('passkey_challenges')->where('id', $row->id)->delete();
            }

            return $row;
        });
        if (! $row || now()->gt($row->expires_at) || (string) $row->user_id !== (string) $request->user()?->id) {
            $this->fail('验证已过期或已使用，请重新开始。');
        }
        $data = json_decode($row->payload, true, 32, JSON_THROW_ON_ERROR);
        if (! hash_equals($config['revision'], $data['revision']) || (string) $data['auth_version'] !== (string) $request->user()?->auth_version) {
            $this->fail('登录配置或账户状态已变化，请重新开始。');
        }

        return $data;
    }

    public function clientData(string $encoded, array $config): string
    {
        $raw = self::decode($encoded);
        $data = json_decode($raw, true, 16);
        if (! is_array($data) || ($data['origin'] ?? null) !== $config['origin'] || ($data['crossOrigin'] ?? false) !== false || isset($data['topOrigin'])) {
            $this->fail();
        }

        return $raw;
    }

    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function decode(string $value): string
    {
        $raw = base64_decode(strtr($value, '-_', '+/'), true);
        if ($value === '' || ! preg_match('/^[A-Za-z0-9_-]+$/D', $value) || $raw === false || self::encode($raw) !== $value) {
            throw ValidationException::withMessages(['passkey' => '通行密钥数据无效，请重新尝试。']);
        }

        return $raw;
    }

    public function fail(string $message = '通行密钥验证未通过，请重试或使用密码登录。'): never
    {
        throw ValidationException::withMessages(['passkey' => $message]);
    }
}

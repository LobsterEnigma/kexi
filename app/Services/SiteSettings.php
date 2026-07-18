<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SiteSettings
{
    private const TYPES = [
        'site_name' => 'string',
        'site_url' => 'nullable_string',
        'timezone' => 'string',
        'session_lifetime_minutes' => 'integer',
        'registration_enabled' => 'boolean',
        'sharing_enabled' => 'boolean',
        'mail_mailer' => 'string',
        'mail_host' => 'string',
        'mail_port' => 'integer',
        'mail_scheme' => 'nullable_string',
        'mail_username' => 'nullable_string',
        'mail_password' => 'secret',
        'mail_from_address' => 'string',
        'mail_from_name' => 'string',
    ];

    public function get(string $key): mixed
    {
        return $this->getMany([$key])[$key];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function getMany(array $keys): array
    {
        foreach ($keys as $key) {
            $this->typeFor($key);
        }

        $stored = SiteSetting::query()
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $values = [];

        foreach ($keys as $key) {
            $setting = $stored->get($key);
            $values[$key] = $setting
                ? $this->cast($setting->value, $this->typeFor($key))
                : config("kexi.settings_defaults.{$key}");
        }

        return $values;
    }

    public function bool(string $key): bool
    {
        $value = $this->get($key);

        if (! is_bool($value)) {
            throw new InvalidArgumentException("Setting {$key} is not boolean.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                $type = $this->typeFor($key);
                SiteSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $this->serialize($value, $type), 'type' => $type],
                );
            }
        });
    }

    public function hasStoredSecret(string $key): bool
    {
        return $this->secretStatus($key) === 'valid';
    }

    public function secretStatus(string $key): string
    {
        if ($this->typeFor($key) !== 'secret') {
            throw new InvalidArgumentException("Setting {$key} is not secret.");
        }

        $value = SiteSetting::query()
            ->where('key', $key)
            ->value('value');

        if (! filled($value)) {
            return 'missing';
        }

        try {
            Crypt::decryptString((string) $value);

            return 'valid';
        } catch (DecryptException) {
            return 'invalid';
        }
    }

    public function revision(): string
    {
        $snapshot = SiteSetting::query()
            ->orderBy('key')
            ->get(['key', 'value', 'type', 'updated_at'])
            ->map(fn (SiteSetting $setting): array => [
                'key' => $setting->key,
                'value' => $setting->type === 'secret'
                    ? hash('sha256', (string) $setting->value)
                    : $setting->value,
                'type' => $setting->type,
                'updated_at' => $setting->updated_at?->format('U.u'),
            ])
            ->all();

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                ?? throw new InvalidArgumentException('Invalid boolean setting value.'),
            'integer' => (int) $value,
            'nullable_string' => filled($value) ? $value : null,
            'secret' => $this->decrypt($value),
            default => (string) $value,
        };
    }

    private function serialize(mixed $value, string $type): ?string
    {
        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'secret' => filled($value) ? Crypt::encryptString((string) $value) : null,
            'nullable_string' => filled($value) ? (string) $value : null,
            default => (string) $value,
        };
    }

    private function decrypt(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    private function typeFor(string $key): string
    {
        return self::TYPES[$key] ?? throw new InvalidArgumentException("Unknown setting: {$key}");
    }
}

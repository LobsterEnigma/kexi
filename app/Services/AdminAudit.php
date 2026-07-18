<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminAudit
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public function record(
        Request $request,
        string $action,
        ?Model $target = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        AdminAuditLog::query()->create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'before' => $this->redact($before),
            'after' => $this->redact($after),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        foreach ($payload as $key => $value) {
            $keyName = (string) $key;
            $isSensitive = preg_match('/password|token|secret|credential|authorization|cookie|api[_-]?key|private[_-]?key/i', $keyName) === 1;
            $isSafeStatus = str_ends_with($keyName, '_configured')
                || str_ends_with($keyName, '_changed')
                || str_ends_with($keyName, '_cleared');

            if ($isSensitive && ! $isSafeStatus) {
                $payload[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->redact($value);
            }
        }

        return $payload;
    }
}

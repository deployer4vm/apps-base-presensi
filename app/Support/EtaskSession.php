<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

final class EtaskSession
{
    public static function put(string $localToken, string $externalToken): void
    {
        Cache::put(
            self::key($localToken),
            Crypt::encryptString($externalToken),
            now()->addMinutes((int) config('session.lifetime', 120))
        );
    }

    public static function get(string $localToken): ?string
    {
        $encrypted = Cache::get(self::key($localToken));
        if (!is_string($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            Cache::forget(self::key($localToken));
            return null;
        }
    }

    public static function forget(string $localToken): void
    {
        Cache::forget(self::key($localToken));
    }

    public static function revoke(string $localToken): void
    {
        $externalToken = self::get($localToken);
        self::forget($localToken);

        if (!$externalToken || !config('services.etask.base_url')) {
            return;
        }

        try {
            Http::baseUrl(rtrim((string) config('services.etask.base_url'), '/'))
                ->acceptJson()
                ->asJson()
                ->withToken($externalToken)
                ->connectTimeout(5)
                ->timeout(10)
                ->withoutRedirecting()
                ->post('/logout');
        } catch (\Throwable $e) {
            // The local session has already been revoked. Upstream expiry is the fallback.
        }
    }

    private static function key(string $localToken): string
    {
        return 'etask_access:' . hash('sha256', $localToken);
    }
}

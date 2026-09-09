<?php

namespace App\Support;

use hpsynapse\moduser\Models\ApiToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

final class EtaskSession
{
    private const SESSION_DATA_KEY = 'etask_access_token';

    public static function put(string $localToken, string $externalToken): void
    {
        $encrypted = Crypt::encryptString($externalToken);

        self::putCache($localToken, $encrypted);
        self::persist($localToken, $encrypted);
    }

    public static function get(string $localToken): ?string
    {
        $encrypted = Cache::get(self::key($localToken));
        if (!is_string($encrypted)) {
            $encrypted = self::persisted($localToken);
        }

        if (!is_string($encrypted)) {
            return null;
        }

        try {
            $externalToken = Crypt::decryptString($encrypted);

            // Keep the eTask mapping alive while the Absensi API session is active.
            self::putCache($localToken, $encrypted);

            return $externalToken;
        } catch (\Throwable $e) {
            self::forget($localToken);
            return null;
        }
    }

    public static function forget(string $localToken): void
    {
        Cache::forget(self::key($localToken));

        $apiToken = self::findLocalToken($localToken);
        if (!$apiToken) {
            return;
        }

        $sessionData = is_array($apiToken->session_data) ? $apiToken->session_data : [];
        unset($sessionData[self::SESSION_DATA_KEY]);
        $apiToken->session_data = $sessionData;
        $apiToken->save();
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
                ->withHeaders(['Authorization' => $externalToken])
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

    private static function putCache(string $localToken, string $encrypted): void
    {
        Cache::put(
            self::key($localToken),
            $encrypted,
            now()->addMinutes((int) config('session.lifetime', 120))
        );
    }

    private static function persist(string $localToken, string $encrypted): void
    {
        $apiToken = self::findLocalToken($localToken);
        if (!$apiToken) {
            return;
        }

        $sessionData = is_array($apiToken->session_data) ? $apiToken->session_data : [];
        $sessionData[self::SESSION_DATA_KEY] = $encrypted;
        $apiToken->session_data = $sessionData;
        $apiToken->save();
    }

    private static function persisted(string $localToken): ?string
    {
        $apiToken = self::findLocalToken($localToken);
        $sessionData = $apiToken && is_array($apiToken->session_data)
            ? $apiToken->session_data
            : [];
        $encrypted = $sessionData[self::SESSION_DATA_KEY] ?? null;

        return is_string($encrypted) ? $encrypted : null;
    }

    private static function findLocalToken(string $localToken): ?ApiToken
    {
        if ($localToken === '') {
            return null;
        }

        return ApiToken::where('api_token', $localToken)->first();
    }
}

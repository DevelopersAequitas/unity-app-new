<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AppleAuthService
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';

    private const JWKS_CACHE_KEY = 'apple.oidc.jwks';

    private const EXPECTED_ISSUER = 'https://appleid.apple.com';

    /**
     * Verify a Sign in with Apple OIDC identityToken JWT via Apple's JWKS keys.
     *
     * @return array{
     *     id: string,
     *     email: ?string,
     *     email_verified: bool,
     *     is_private_email: bool,
     *     first_name: string,
     *     last_name: string,
     *     name: string,
     *     avatar: ?string
     * }
     *
     * @throws RuntimeException
     */
    public function verifyIdentityToken(string $identityToken, ?string $nonce = null): array
    {
        $jwt = trim($identityToken);
        if ($jwt === '') {
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        $segments = explode('.', $jwt);
        if (count($segments) !== 3 || $segments[0] === '' || $segments[1] === '' || $segments[2] === '') {
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($header) || ! is_array($claims)) {
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        $alg = strtoupper((string) ($header['alg'] ?? ''));
        $kid = trim((string) ($header['kid'] ?? ''));

        if ($alg !== 'RS256' || $kid === '') {
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        $jwk = $this->resolveJwkByKid($kid);
        $pem = $this->jwkToPem($jwk);

        $signature = $this->base64UrlDecode($encodedSignature);
        $verified = openssl_verify("{$encodedHeader}.{$encodedPayload}", $signature, $pem, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            Log::warning('AppleAuthService: OIDC JWT signature verification failed');
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        if ((string) ($claims['iss'] ?? '') !== self::EXPECTED_ISSUER) {
            Log::warning('AppleAuthService: OIDC JWT issuer mismatch');
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        $allowedAudiences = $this->allowedAudiences();
        if ($allowedAudiences !== [] && ! $this->audienceMatches($claims['aud'] ?? null, $allowedAudiences)) {
            Log::warning('AppleAuthService: OIDC JWT audience mismatch');
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        $exp = $claims['exp'] ?? null;
        if (! is_numeric($exp) || (int) $exp <= now()->timestamp) {
            Log::warning('AppleAuthService: OIDC JWT expired or missing exp');
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        if ($nonce !== null && trim($nonce) !== '') {
            $trimmedNonce = trim($nonce);
            $claimNonce = isset($claims['nonce']) ? (string) $claims['nonce'] : '';
            $hashedNonce = hash('sha256', $trimmedNonce);

            if (
                $claimNonce === ''
                || (! hash_equals($trimmedNonce, $claimNonce) && ! hash_equals($hashedNonce, $claimNonce))
            ) {
                Log::warning('AppleAuthService: OIDC JWT nonce mismatch');
                throw new RuntimeException('Invalid or expired Apple token.');
            }
        }

        $sub = isset($claims['sub']) ? trim((string) $claims['sub']) : '';
        if ($sub === '') {
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        $rawEmail = isset($claims['email']) ? strtolower(trim((string) $claims['email'])) : '';
        $email = $rawEmail !== '' ? $rawEmail : null;

        return [
            'id' => $sub,
            'email' => $email,
            'email_verified' => $this->toBoolClaim($claims['email_verified'] ?? true),
            'is_private_email' => $this->toBoolClaim($claims['is_private_email'] ?? false),
            'first_name' => '',
            'last_name' => '',
            'name' => '',
            'avatar' => null,
        ];
    }

    /**
     * Resolve the Android package name for Apple Chrome Custom Tab intent redirect.
     */
    public function resolveAndroidPackageForHost(string $host, ?string $state = null): string
    {
        $normalizedHost = strtolower(trim($host));
        $normalizedState = strtolower(trim((string) ($state ?? '')));

        if (str_starts_with($normalizedHost, 'dev.') || str_contains($normalizedState, 'dev')) {
            return trim((string) config('services.apple.android_dev_package', 'com.peers.peersunity.dev'))
                ?: 'com.peers.peersunity.dev';
        }

        return trim((string) config('services.apple.android_package', 'com.peers.peersunity'))
            ?: 'com.peers.peersunity';
    }

    /**
     * @return array<int, string>
     */
    protected function allowedAudiences(): array
    {
        $candidates = [
            config('services.apple.client_id'),
            config('services.apple.bundle_id'),
            config('services.apple.dev_bundle_id'),
        ];

        $extra = config('services.apple.allowed_audiences', []);
        if (is_array($extra)) {
            $candidates = array_merge($candidates, $extra);
        }

        $normalized = [];
        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>  $allowedAudiences
     */
    protected function audienceMatches(mixed $aud, array $allowedAudiences): bool
    {
        if (is_string($aud)) {
            return in_array($aud, $allowedAudiences, true);
        }

        if (is_array($aud)) {
            foreach ($aud as $value) {
                if (in_array((string) $value, $allowedAudiences, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function resolveJwkByKid(string $kid): array
    {
        $jwks = Cache::remember(self::JWKS_CACHE_KEY, now()->addHours(3), fn (): array => $this->fetchJwks());
        $matched = $this->findKeyInJwks($jwks, $kid);

        if ($matched !== null) {
            return $matched;
        }

        $freshJwks = $this->fetchJwks();
        Cache::put(self::JWKS_CACHE_KEY, $freshJwks, now()->addHours(3));
        $matched = $this->findKeyInJwks($freshJwks, $kid);

        if ($matched === null) {
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        return $matched;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws RuntimeException
     */
    protected function fetchJwks(): array
    {
        $response = Http::timeout(10)->get(self::JWKS_URL);
        if ($response->failed()) {
            Log::warning('AppleAuthService: Failed to fetch Apple JWKS', [
                'status' => $response->status(),
            ]);
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        $keys = $response->json('keys');
        if (! is_array($keys)) {
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        return array_values(array_filter($keys, 'is_array'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $keys
     * @return array<string, mixed>|null
     */
    protected function findKeyInJwks(array $keys, string $kid): ?array
    {
        foreach ($keys as $key) {
            if ((string) ($key['kid'] ?? '') === $kid && strtoupper((string) ($key['kty'] ?? '')) === 'RSA') {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $jwk
     *
     * @throws RuntimeException
     */
    protected function jwkToPem(array $jwk): string
    {
        $modulusEncoded = (string) ($jwk['n'] ?? '');
        $exponentEncoded = (string) ($jwk['e'] ?? '');

        if ($modulusEncoded === '' || $exponentEncoded === '') {
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        $modulus = $this->base64UrlDecode($modulusEncoded);
        $exponent = $this->base64UrlDecode($exponentEncoded);

        $rsaPublicKey = $this->encodeDerSequence(
            $this->encodeDerInteger($modulus).$this->encodeDerInteger($exponent)
        );

        $algorithmIdentifier = $this->encodeDerSequence(
            "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"
        );

        $bitString = "\x03".$this->encodeDerLength(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey;
        $subjectPublicKeyInfo = $this->encodeDerSequence($algorithmIdentifier.$bitString);

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    protected function encodeDerInteger(string $unsignedBigEndianBytes): string
    {
        $trimmed = ltrim($unsignedBigEndianBytes, "\x00");
        if ($trimmed === '') {
            $trimmed = "\x00";
        } elseif ((ord($trimmed[0]) & 0x80) !== 0) {
            $trimmed = "\x00".$trimmed;
        }

        return "\x02".$this->encodeDerLength(strlen($trimmed)).$trimmed;
    }

    protected function encodeDerSequence(string $content): string
    {
        return "\x30".$this->encodeDerLength(strlen($content)).$content;
    }

    protected function encodeDerLength(int $length): string
    {
        if ($length <= 0x7F) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    protected function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder > 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($input, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid or expired Apple token.');
        }

        return $decoded;
    }

    protected function toBoolClaim(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return strtolower(trim((string) $value)) === 'true';
    }
}

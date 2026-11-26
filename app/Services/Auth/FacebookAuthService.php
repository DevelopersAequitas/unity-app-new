<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FacebookAuthService
{
    private const JWKS_URL = 'https://www.facebook.com/.well-known/oauth/openid/jwks/';

    private const JWKS_CACHE_KEY = 'facebook.oidc.jwks';

    private const EXPECTED_ISSUER = 'https://www.facebook.com';

    /**
     * Verify a Facebook OAuth access token (classic) or iOS Limited Login OIDC JWT.
     *
     * @return array{
     *     id: string,
     *     email: ?string,
     *     name: string,
     *     first_name: string,
     *     last_name: string,
     *     avatar: ?string,
     *     dob: ?string,
     *     location: ?string,
     *     token_type: string
     * }
     *
     * @throws RuntimeException
     */
    public function verifyToken(string $accessToken, string $tokenType = 'classic', ?string $nonce = null): array
    {
        $trimmedToken = trim($accessToken);
        if ($trimmedToken === '') {
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $normalizedType = strtolower(trim($tokenType));
        if ($normalizedType === '') {
            $normalizedType = 'classic';
        }

        return match ($normalizedType) {
            'classic' => $this->verifyClassicToken($trimmedToken),
            'limited' => $this->verifyLimitedLoginJwt($trimmedToken, $nonce),
            default => throw new RuntimeException('Unsupported Facebook token type.'),
        };
    }

    /**
     * Parse Facebook birthday string strictly for full MM/DD/YYYY (or YYYY-MM-DD) dates.
     * Ignores partial dates such as MM/DD or YYYY.
     */
    public function parseBirthday(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $trimmed, $matches) === 1) {
            $month = (int) $matches[1];
            $day = (int) $matches[2];
            $year = (int) $matches[3];

            if ($year < 1900 || ! checkdate($month, $day, $year)) {
                return null;
            }

            $date = Carbon::createFromDate($year, $month, $day)->startOfDay();

            return $date->lte(now()->endOfDay()) ? $date->format('Y-m-d') : null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmed, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            if ($year < 1900 || ! checkdate($month, $day, $year)) {
                return null;
            }

            $date = Carbon::createFromDate($year, $month, $day)->startOfDay();

            return $date->lte(now()->endOfDay()) ? $date->format('Y-m-d') : null;
        }

        return null;
    }

    /**
     * Verify Meta Data Deletion Callback signed_request (HMAC-SHA256).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function verifySignedRequest(string $signedRequest): array
    {
        $clientSecret = trim((string) config('services.facebook.client_secret', ''));
        if ($clientSecret === '') {
            throw new RuntimeException('Facebook client secret is not configured.');
        }

        $parts = explode('.', trim($signedRequest), 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new RuntimeException('Invalid signed_request format.');
        }

        [$encodedSig, $encodedPayload] = $parts;

        $sig = $this->base64UrlDecode($encodedSig);
        $decodedPayload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($decodedPayload) || strtoupper((string) ($decodedPayload['algorithm'] ?? '')) !== 'HMAC-SHA256') {
            throw new RuntimeException('Unsupported signed_request algorithm.');
        }

        $expectedSig = hash_hmac('sha256', $encodedPayload, $clientSecret, true);
        if (! hash_equals($expectedSig, $sig)) {
            throw new RuntimeException('Invalid signed_request signature.');
        }

        return $decodedPayload;
    }

    /**
     * Path A: Verify Classic OAuth access token via debug_token (when configured) and Graph /me.
     *
     * @return array{
     *     id: string,
     *     email: ?string,
     *     name: string,
     *     first_name: string,
     *     last_name: string,
     *     avatar: ?string,
     *     dob: ?string,
     *     location: ?string,
     *     token_type: string
     * }
     *
     * @throws RuntimeException
     */
    protected function verifyClassicToken(string $accessToken): array
    {
        $clientId = trim((string) config('services.facebook.client_id', ''));
        $clientSecret = trim((string) config('services.facebook.client_secret', ''));
        $graphVersion = trim((string) config('services.facebook.graph_version', 'v19.0')) ?: 'v19.0';

        $debugUserId = null;

        if ($clientId !== '' && $clientSecret !== '') {
            $debugResponse = Http::timeout(10)->get('https://graph.facebook.com/debug_token', [
                'input_token' => $accessToken,
                'access_token' => "{$clientId}|{$clientSecret}",
            ]);

            if ($debugResponse->failed()) {
                Log::warning('FacebookAuthService: debug_token HTTP failure', [
                    'status' => $debugResponse->status(),
                ]);
                throw new RuntimeException('Invalid or expired Facebook token.');
            }

            $debugData = $debugResponse->json('data');
            if (! is_array($debugData) || ($debugData['is_valid'] ?? false) !== true) {
                Log::warning('FacebookAuthService: debug_token reported invalid token');
                throw new RuntimeException('Invalid or expired Facebook token.');
            }

            if ((string) ($debugData['app_id'] ?? '') !== $clientId) {
                Log::warning('FacebookAuthService: debug_token app_id mismatch', [
                    'expected' => $clientId,
                    'actual' => (string) ($debugData['app_id'] ?? ''),
                ]);
                throw new RuntimeException('Invalid or expired Facebook token.');
            }

            $expiresAt = (int) ($debugData['expires_at'] ?? 0);
            if ($expiresAt > 0 && $expiresAt <= now()->timestamp) {
                Log::warning('FacebookAuthService: debug_token token expired');
                throw new RuntimeException('Invalid or expired Facebook token.');
            }

            $debugUserId = isset($debugData['user_id']) ? trim((string) $debugData['user_id']) : null;
        }

        $meResponse = Http::timeout(10)->get("https://graph.facebook.com/{$graphVersion}/me", [
            'fields' => 'id,name,first_name,last_name,email,picture.width(400),birthday,location',
            'access_token' => $accessToken,
        ]);

        if ($meResponse->failed()) {
            Log::warning('FacebookAuthService: Graph /me call failed', [
                'status' => $meResponse->status(),
            ]);
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $meData = $meResponse->json();
        $id = is_array($meData) && isset($meData['id']) ? trim((string) $meData['id']) : '';

        if ($id === '') {
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        if ($debugUserId !== null && $debugUserId !== '' && $id !== $debugUserId) {
            Log::warning('FacebookAuthService: Graph /me id does not match debug_token user_id');
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $fullName = trim((string) ($meData['name'] ?? ''));
        $firstName = trim((string) ($meData['first_name'] ?? ''));
        $lastName = trim((string) ($meData['last_name'] ?? ''));

        if ($firstName === '' && $lastName === '' && $fullName !== '') {
            [$firstName, $lastName] = $this->splitFullName($fullName);
        }

        $rawEmail = isset($meData['email']) ? strtolower(trim((string) $meData['email'])) : '';
        $email = $rawEmail !== '' ? $rawEmail : null;

        $avatarUrl = isset($meData['picture']['data']['url']) && trim((string) $meData['picture']['data']['url']) !== ''
            ? trim((string) $meData['picture']['data']['url'])
            : null;

        $dob = $this->parseBirthday(isset($meData['birthday']) && is_string($meData['birthday']) ? $meData['birthday'] : null);

        $location = isset($meData['location']['name']) && is_string($meData['location']['name']) && trim($meData['location']['name']) !== ''
            ? trim($meData['location']['name'])
            : null;

        return [
            'id' => $id,
            'email' => $email,
            'name' => $fullName !== '' ? $fullName : trim("{$firstName} {$lastName}"),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'avatar' => $avatarUrl,
            'dob' => $dob,
            'location' => $location,
            'token_type' => 'classic',
        ];
    }

    /**
     * Path B: Verify iOS Limited Login OIDC JWT via Meta JWKS and strict claims validation.
     *
     * @return array{
     *     id: string,
     *     email: ?string,
     *     name: string,
     *     first_name: string,
     *     last_name: string,
     *     avatar: ?string,
     *     dob: ?string,
     *     location: ?string,
     *     token_type: string
     * }
     *
     * @throws RuntimeException
     */
    protected function verifyLimitedLoginJwt(string $jwt, ?string $nonce = null): array
    {
        $segments = explode('.', $jwt);
        if (count($segments) !== 3 || $segments[0] === '' || $segments[1] === '' || $segments[2] === '') {
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($header) || ! is_array($claims)) {
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $alg = strtoupper((string) ($header['alg'] ?? ''));
        $kid = trim((string) ($header['kid'] ?? ''));

        if ($alg !== 'RS256' || $kid === '') {
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $jwk = $this->resolveJwkByKid($kid);
        $pem = $this->jwkToPem($jwk);

        $signature = $this->base64UrlDecode($encodedSignature);
        $verified = openssl_verify("{$encodedHeader}.{$encodedPayload}", $signature, $pem, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            Log::warning('FacebookAuthService: OIDC JWT signature verification failed');
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        if ((string) ($claims['iss'] ?? '') !== self::EXPECTED_ISSUER) {
            Log::warning('FacebookAuthService: OIDC JWT issuer mismatch');
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $clientId = trim((string) config('services.facebook.client_id', ''));
        if ($clientId !== '' && ! $this->audienceMatches($claims['aud'] ?? null, $clientId)) {
            Log::warning('FacebookAuthService: OIDC JWT audience mismatch');
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $exp = $claims['exp'] ?? null;
        if (! is_numeric($exp) || (int) $exp <= now()->timestamp) {
            Log::warning('FacebookAuthService: OIDC JWT expired or missing exp');
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        if ($nonce !== null && trim($nonce) !== '') {
            $claimNonce = isset($claims['nonce']) ? (string) $claims['nonce'] : '';
            if ($claimNonce === '' || ! hash_equals(trim($nonce), $claimNonce)) {
                Log::warning('FacebookAuthService: OIDC JWT nonce mismatch');
                throw new RuntimeException('Invalid or expired Facebook token.');
            }
        }

        $sub = isset($claims['sub']) ? trim((string) $claims['sub']) : '';
        if ($sub === '') {
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $fullName = trim((string) ($claims['name'] ?? ''));
        $firstName = trim((string) ($claims['given_name'] ?? ''));
        $lastName = trim((string) ($claims['family_name'] ?? ''));

        if ($firstName === '' && $lastName === '' && $fullName !== '') {
            [$firstName, $lastName] = $this->splitFullName($fullName);
        }

        $rawEmail = isset($claims['email']) ? strtolower(trim((string) $claims['email'])) : '';
        $email = $rawEmail !== '' ? $rawEmail : null;

        $avatarUrl = isset($claims['picture']) && is_string($claims['picture']) && trim($claims['picture']) !== ''
            ? trim($claims['picture'])
            : null;

        return [
            'id' => $sub,
            'email' => $email,
            'name' => $fullName !== '' ? $fullName : trim("{$firstName} {$lastName}"),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'avatar' => $avatarUrl,
            'dob' => null,
            'location' => null,
            'token_type' => 'limited',
        ];
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

        // Key rotation fallback: bypass stale cache once and re-fetch JWKS.
        $freshJwks = $this->fetchJwks();
        Cache::put(self::JWKS_CACHE_KEY, $freshJwks, now()->addHours(3));
        $matched = $this->findKeyInJwks($freshJwks, $kid);

        if ($matched === null) {
            throw new RuntimeException('Invalid or expired Facebook token.');
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
            Log::warning('FacebookAuthService: Failed to fetch Meta OIDC JWKS', [
                'status' => $response->status(),
            ]);
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $keys = $response->json('keys');
        if (! is_array($keys)) {
            throw new RuntimeException('Invalid or expired Facebook token.');
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
     * Convert an RSA JWK (n, e) into an X.509 SubjectPublicKeyInfo PEM string.
     *
     * @param  array<string, mixed>  $jwk
     *
     * @throws RuntimeException
     */
    protected function jwkToPem(array $jwk): string
    {
        $modulusEncoded = (string) ($jwk['n'] ?? '');
        $exponentEncoded = (string) ($jwk['e'] ?? '');

        if ($modulusEncoded === '' || $exponentEncoded === '') {
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        $modulus = $this->base64UrlDecode($modulusEncoded);
        $exponent = $this->base64UrlDecode($exponentEncoded);

        $rsaPublicKey = $this->encodeDerSequence(
            $this->encodeDerInteger($modulus).$this->encodeDerInteger($exponent)
        );

        // OID 1.2.840.113549.1.1.1 (rsaEncryption) + NULL
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
            throw new RuntimeException('Invalid or expired Facebook token.');
        }

        return $decoded;
    }

    protected function audienceMatches(mixed $aud, string $clientId): bool
    {
        if (is_string($aud)) {
            return $aud === $clientId;
        }

        if (is_array($aud)) {
            return in_array($clientId, array_map('strval', $aud), true);
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);
        if (! is_array($parts)) {
            return ['', ''];
        }

        return [
            trim((string) ($parts[0] ?? '')),
            trim((string) ($parts[1] ?? '')),
        ];
    }
}

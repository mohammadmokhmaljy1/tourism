<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * Jwt.php
 * Lightweight HS256 JWT encode/decode (no external dependencies).
 */

class Jwt
{
    /**
     * Issues a signed JWT for the given user id.
     *
     * @return array{token:string,jti:string,expires_at:int}
     */
    public static function issue(int $userId): array
    {
        $now   = time();
        $jti   = bin2hex(random_bytes(16));
        $exp   = $now + JwtConfig::TTL_SECONDS;

        $payload = [
            'iss' => JwtConfig::ISSUER,
            'sub' => $userId,
            'jti' => $jti,
            'iat' => $now,
            'exp' => $exp,
        ];

        return [
            'token'      => self::encode($payload),
            'jti'        => $jti,
            'expires_at' => $exp,
        ];
    }

    /**
     * Decodes and verifies a Bearer token.
     *
     * @return array{sub:int,jti:string,exp:int}
     */
    public static function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            Response::unauthorized('Invalid or malformed token.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "{$headerB64}.{$payloadB64}", JwtConfig::SECRET, true)
        );

        if (!hash_equals($expectedSig, $signatureB64)) {
            Response::unauthorized('Invalid token signature.');
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);

        if (!is_array($payload)) {
            Response::unauthorized('Invalid token payload.');
        }

        if (($payload['iss'] ?? '') !== JwtConfig::ISSUER) {
            Response::unauthorized('Invalid token issuer.');
        }

        if (!isset($payload['exp']) || (int) $payload['exp'] < time()) {
            Response::unauthorized('Token has expired.');
        }

        if (!isset($payload['sub'], $payload['jti'])) {
            Response::unauthorized('Invalid token claims.');
        }

        return [
            'sub' => (int) $payload['sub'],
            'jti' => (string) $payload['jti'],
            'exp' => (int) $payload['exp'],
        ];
    }

    /**
     * Reads the JWT from the request.
     *
     * Priority:
     *   1. Authorization: Bearer {token}
     *   2. X-Auth-Token: {token}  (fallback when the host blocks Authorization)
     */
    public static function bearerToken(): ?string
    {
        $authHeader = self::requestHeader('Authorization');

        if ($authHeader !== null && preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        $xAuthToken = self::requestHeader('X-Auth-Token');

        return ($xAuthToken !== null && $xAuthToken !== '') ? $xAuthToken : null;
    }

    /**
     * Decodes the Bearer token from the current request or sends 401.
     *
     * @return array{sub:int,jti:string,exp:int}
     */
    public static function requireBearer(): array
    {
        $token = self::bearerToken();

        if ($token === null) {
            Response::unauthorized(
                'Authorization token is required. Send Authorization: Bearer {token} or X-Auth-Token: {token}.'
            );
        }

        return self::decode($token);
    }

    /**
     * Reads a request header in a way that works across Apache, LiteSpeed, and CGI.
     */
    private static function requestHeader(string $name): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (!empty($_SERVER[$serverKey])) {
            return trim((string) $_SERVER[$serverKey]);
        }

        if (strcasecmp($name, 'Authorization') === 0 && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (strcasecmp((string) $key, $name) === 0) {
                        return trim((string) $value);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function encode(array $payload): string
    {
        $header  = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body    = self::base64UrlEncode(json_encode($payload));
        $sig     = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$body}", JwtConfig::SECRET, true)
        );

        return "{$header}.{$body}.{$sig}";
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded !== false ? $decoded : '';
    }
}

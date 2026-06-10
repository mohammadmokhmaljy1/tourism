<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * JwtConfig.php
 * Central JWT settings. Change SECRET in production.
 */

class JwtConfig
{
    /** HMAC signing secret — MUST be changed on the live server. */
    public const SECRET = 'devmmnd-tourism-jwt-secret-change-in-production';

    /** Token lifetime in seconds (7 days). */
    public const TTL_SECONDS = 604800;

    /** Issuer claim embedded in every token. */
    public const ISSUER = 'devmmnd-tourism-api';
}

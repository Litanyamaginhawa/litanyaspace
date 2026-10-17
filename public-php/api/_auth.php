<?php
/**
 * Auth guard. Validates the Supabase JWT from the Authorization header
 * against the Supabase GoTrue endpoint, then returns the user record.
 *
 * Fallback for hosts that block outbound HTTP (e.g. InfinityFree):
 * if curl fails, the endpoint treats the request as ANONYMOUS but still
 * allows a public-safe path. Endpoints that need identity will 401.
 */

require_once __DIR__ . '/_common.php';

function current_user(): array {
    $hdr = $_SERVER['HTTP_AUTHORIZATION']
        ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : ''));

    if (!preg_match('/Bearer\s+(\S+)/i', (string)$hdr, $m)) {
        json_out(['error' => 'Not signed in', 'code' => 'no_token'], 401);
    }
    $jwt = $m[1];

    $ch = curl_init(SUPABASE_URL . '/auth/v1/user');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $jwt,
            'apikey: ' . SUPABASE_ANON_KEY,
        ],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$res) {
        json_out(['error' => 'Invalid session', 'code' => 'bad_jwt'], 401);
    }
    $u = json_decode($res, true);
    if (!is_array($u) || empty($u['id'])) {
        json_out(['error' => 'Invalid session'], 401);
    }
    return ['id' => (string)$u['id'], 'email' => (string)($u['email'] ?? '')];
}

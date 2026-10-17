<?php
/** Shared helpers for every /api/*.php endpoint. */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ALLOW_ORIGIN);
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_out(array $payload, int $code = 200): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_in(): array {
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function query(string $key, string $default = ''): string {
    return trim((string)($_GET[$key] ?? $default));
}

function body(string $key, ?string $default = null): ?string {
    $v = json_in()[$key] ?? $default;
    return is_scalar($v) ? (string)$v : $default;
}

function require_field(string $key, array $data): string {
    $v = trim((string)($data[$key] ?? ''));
    if ($v === '') json_out(['error' => "Missing field: $key"], 400);
    return $v;
}

/** Generate a UUID v4 string (MySQL-compatible, CHAR(36)). */
function uuid_v4(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function now_maneila(): string {
    return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
}

<?php
/**
 * /api/recompute_trending.php
 *
 * Recomputes the 24h trending hashtags/words from Supabase posts and
 * updates `trending_cache`. Call from your cPanel Cron Job:
 *   */30 * * * * curl -s https://yoursite/api/recompute_trending.php?key=CRON_SECRET
 *
 * Pass ?key=CRON_SECRET (defined below) or run via PHP CLI without key.
 *
 * Pulls recent posts, tokenizes content + first-word of topics, and
 * writes the top 50 tags.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_common.php';

const CRON_SECRET = 'CHANGE_ME_TO_A_LONG_RANDOM_STRING';
const WINDOW_HOURS = 24;
const TOP_N        = 50;
const FETCH_LIMIT  = 2000;

// Only allow from CLI or with the correct key.
$isCli = (PHP_SAPI === 'cli');
$key   = (string)($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');
if (!$isCli && !hash_equals(CRON_SECRET, $key)) {
    json_out(['error' => 'Forbidden'], 403);
}

// 1) Fetch recent posts via Supabase REST.
$since = (new DateTime('-' . WINDOW_HOURS . ' hours'))
    ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
$url = SUPABASE_URL . "/rest/v1/posts"
     . "?select=content,kind,created_at"
     . "&created_at=gt.$since&limit=$FETCH_LIMIT";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
    ],
]);
$res = curl_exec($ch); curl_close($ch);
$rows = json_decode($res ?: '[]', true);
if (!is_array($rows)) json_out(['error' => 'Supabase fetch failed'], 502);

// 2) Tokenize content for #hashtags and top words.
$counts = [];
foreach ($rows as $r) {
    $text = (string)($r['content'] ?? '');
    if (preg_match_all('/#([\p{L}\p{N}_]{2,40})/u', $text, $m)) {
        foreach ($m[1] as $tag) {
            $tag = mb_strtolower($tag);
            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
        }
    }
}
arsort($counts);
$top = array_slice($counts, 0, TOP_N, true);

// 3) Write trending_cache (full replace inside a transaction).
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM trending_cache');
    $ins = $pdo->prepare('INSERT INTO trending_cache (tag, count_24h, updated_at) VALUES (?, ?, NOW())');
    foreach ($top as $tag => $n) $ins->execute([$tag, (int)$n]);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); throw $e; }

json_out([
    'ok'         => true,
    'scanned'    => count($rows),
    'written'    => count($top),
    'updated_at' => now_maneila(),
]);

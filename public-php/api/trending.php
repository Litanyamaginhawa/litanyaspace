<?php
/**
 * /api/trending.php  - returns top hashtags/words cached by a cron job.
 *  GET  -> { tags: [{tag, count_24h}, ...] }
 *
 * The Supabase frontend should call this ONCE per app open and show
 * the result in the composer as "Trending in LitanyaSpace" chips,
 * the same way IG/FB suggest hashtags as you type.
 *
 * The cache itself is refreshed by /api/recompute_trending.php, which
 * you run from your cPanel Cron Job every 30 minutes.
 */
require_once __DIR__ . '/_common.php';

$limit = max(5, min(50, (int)query('limit', '20')));
$st = db()->prepare('SELECT tag, count_24h FROM trending_cache ORDER BY count_24h DESC LIMIT ?');
$st->bindValue(1, $limit, PDO::PARAM_INT);
$st->execute();
json_out(['tags' => $st->fetchAll(), 'generated_at' => now_maneila()]);

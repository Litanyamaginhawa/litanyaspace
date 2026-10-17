<?php
/**
 * /api/audio.php  - open-source music search for the post composer
 *
 * The frontend's audio selector ("parang MP3Juice pero legal") uses
 * this endpoint. It proxies Jamendo's public Creative Commons catalog.
 *
 * Endpoints:
 *   GET  ?q=&tag=&limit=10&offset=0
 *        -> { tracks: [{id, title, artist, url(30s mp3), duration_ms,
 *                       license, attribution}, ...] }
 *   GET  &id=<jamendo_track_id>&save=1
 *        -> returns the full JSON AND saves it to audio_library
 *           (so the user can reuse it without re-searching)
 *   GET  ?me=1
 *        -> user's saved tracks from audio_library (auth)
 *   DELETE ?id=<uuid>   (auth, owner) -> soft-unpin
 *
 * Setup: get a free Jamendo client_id at
 *   https://dev.jamendo.com/en/accounts/register/
 * then paste it into JAMENDO_CLIENT_ID in ../config.php.
 */
require_once __DIR__ . '/_auth.php';

const JAMENDO_SEARCH = 'https://api.jamendo.com/v3.0/tracks/';

function jamendo_get(array $params): array {
    if (!defined('JAMENDO_CLIENT_ID') || JAMENDO_CLIENT_ID === '') {
        return ['error' => 'Jamendo client_id not configured. Add it to config.php.'];
    }
    $params += [
        'client_id'   => JAMENDO_CLIENT_ID,
        'format'      => 'JSON',
        'audioformat' => 'mp32',
        'limit'       => 20,
        'offset'      => 0,
        'index_mode'  => 'pop',
    ];
    $url = JAMENDO_SEARCH . '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $res = curl_exec($ch);
    curl_close($ch);
    $j = json_decode($res ?: '{}', true);
    return is_array($j) ? $j : ['error' => 'Upstream returned non-JSON'];
}

function normalize_track(array $t): array {
    return [
        'id'           => (string)($t['id'] ?? ''),
        'title'        => (string)($t['name'] ?? ''),
        'artist'       => (string)($t['artist_name'] ?? ''),
        'url'          => (string)($t['audio'] ?? ''),       // 30s preview mp3
        'artwork'      => (string)($t['image'] ?? ''),
        'duration_ms'  => (int)($t['duration'] ?? 0) * 1000,
        'license'      => (string)($t['license'] ?? 'CC'),
        'attribution'  => trim(($t['artist_name'] ?? '') . ' — ' . ($t['name'] ?? '')),
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id   = query('id');
    $q    = query('q');
    $tag  = query('tag');
    $me   = query('me');
    $save = query('save');
    $limit  = max(1, min(20, (int)query('limit', '10')));
    $offset = max(0, (int)query('offset', '0'));

    // 1) Return the user's saved/pinned tracks
    if ($me === '1') {
        $u = current_user();
        $st = db()->prepare(
            'SELECT * FROM audio_library WHERE provider = ? AND added_by = ? AND is_active = 1
             ORDER BY created_at DESC LIMIT 50'
        );
        $st->execute(['jamendo', $u['id']]);
        json_out(['tracks' => $st->fetchAll()]);
    }

    // 2) Save a single Jamendo track (proxy + upsert into library)
    if ($id !== '' && $save === '1') {
        $u = current_user();
        $j = jamendo_get(['id' => $id]);
        if (!empty($j['error'])) json_out($j, 502);
        $raw = $j['results'][0] ?? null;
        if (!$raw) json_out(['error' => 'Track not found'], 404);
        $t = normalize_track($raw);

        // Upsert: same provider+external_id => reactivate/update
        $sel = db()->prepare('SELECT id FROM audio_library WHERE provider = ? AND external_id = ?');
        $sel->execute(['jamendo', $t['id']]);
        $existing = $sel->fetch();
        if ($existing) {
            db()->prepare('UPDATE audio_library SET is_active = 1, url = ?, title = ?, artist = ?, duration_ms = ?, license = ?, attribution = ?, created_at = NOW() WHERE id = ?')
                ->execute([$t['url'], $t['title'], $t['artist'], $t['duration_ms'], $t['license'], $t['attribution'], $existing['id']]);
            json_out(['ok' => true, 'id' => $existing['id'], 'track' => $t]);
        }
        $newId = uuid_v4();
        db()->prepare(
            'INSERT INTO audio_library (id, provider, external_id, title, artist, url, duration_ms, license, attribution, added_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $newId, 'jamendo', $t['id'], $t['title'], $t['artist'],
            $t['url'], $t['duration_ms'], $t['license'], $t['attribution'], $u['id'],
        ]);
        json_out(['ok' => true, 'id' => $newId, 'track' => $t], 201);
    }

    // 3) Public search proxy (no auth needed)
    if ($q !== '') {
        $j = jamendo_get(['search' => $q, 'limit' => $limit, 'offset' => $offset]);
    } elseif ($tag !== '') {
        $j = jamendo_get(['tag' => $tag, 'limit' => $limit, 'offset' => $offset]);
    } else {
        // Default: "calm,ambient" trending when no query yet
        $j = jamendo_get(['tag' => 'calm', 'limit' => $limit, 'offset' => $offset]);
    }
    if (!empty($j['error'])) json_out($j, 502);
    $out = array_map('normalize_track', $j['results'] ?? []);
    json_out(['tracks' => $out, 'total' => count($out)]);
}

if ($method === 'DELETE') {
    $id = query('id');
    if ($id === '') json_out(['error' => 'Missing id'], 400);
    $u = current_user();
    $st = db()->prepare('SELECT added_by FROM audio_library WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row || $row['added_by'] !== $u['id']) json_out(['error' => 'Forbidden'], 403);
    db()->prepare('UPDATE audio_library SET is_active = 0 WHERE id = ?')->execute([$id]);
    json_out(['ok' => true]);
}

json_out(['error' => 'Method not allowed'], 405);

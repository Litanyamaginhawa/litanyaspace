<?php
/**
 * LitanyaSpace - PHP config (edit these values)
 * Place this whole /public-php folder inside htdocs/ of your InfinityFree (or any PHP host).
 */

declare(strict_types=1);

// -- Supabase (your existing project - do NOT change these unless you migrated) --
const SUPABASE_URL      = 'https://gmjtpvxwymevjsicbwlh.supabase.co';
const SUPABASE_ANON_KEY = 'REPLACE_WITH_YOUR_SUPABASE_ANON_KEY';

// -- MySQL (from InfinityFree cPanel > MySQL Databases) --
const DB_HOST = 'sql###.main-hosting.io';
const DB_NAME = 'ep2_########_litanya';
const DB_USER = 'ep2_########';
const DB_PASS = 'YOUR_DB_PASSWORD';
const DB_CHARSET = 'utf8mb4';

// -- CORS: change to your real InfinityFree domain or custom domain --
const ALLOW_ORIGIN = '*';   // e.g. 'https://litanya.infinityfreeapp.com'

// -- Jamendo (open-source music for the audio selector) --
// Get a FREE client_id: https://dev.jamendo.com/en/accounts/register/
const JAMENDO_CLIENT_ID = 'REPLACE_WITH_JAMENDO_CLIENT_ID';

// -- App-wide --
const APP_NAME = 'LitanyaSpace';
const DEBUG    = false;     // set true ONLY during development

date_default_timezone_set('Asia/Manila');

// -- DB connection (singleton) --
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

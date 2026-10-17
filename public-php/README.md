# LitanyaSpace — PHP layer (InfinityFree deploy kit)

Ang PHP/MySQL na ito ay **thin add-on** lang sa existing Supabase app mo.
Hindi nito pinapalitan ang Supabase — dinadagdag lang nito ang mga bagay
na hindi kaya ng Supabase nang mura:

- **Trending hashtags** (cached, cron-refreshed) → para makita ng user
  ang "Trending in LitanyaSpace" chips sa composer, parang IG/FB.
- **Daily stats** (DAU, posts/day, messages/day) → admin dashboard.
- **Audio library** (open-source music via Jamendo) → parang MP3Juice
  search, pero legal at CC-licensed.

## Files

```
public-php/
  config.php                 <-- edit these values before upload
  schema.sql                 <-- import into phpMyAdmin
  api/
    _common.php              (shared helpers)
    _auth.php                (Supabase JWT validator)
    trending.php             GET /api/trending.php
    recompute_trending.php   (cron)
    audio.php                GET /api/audio.php?q=... | ?save=1 | ?me=1
```

The **frontend** (index.html) keeps calling Supabase for posts, messages,
notifications, etc. Tatanungin lang niya ang PHP layer para sa trending
at audio.

## Deploy (once)

1. **Sign up at InfinityFree** → create site (free subdomain or custom
   domain).
2. **cPanel → MySQL Databases**
   - Create DB (say `ep2_XXXXXX_litanya`).
   - Create DB user + password, **add user to DB** with *ALL PRIVILEGES*.
3. **cPanel → File Manager → `htdocs/`**
   - Upload your existing `index.html`, `sw.js`, `manifest.json`, icons.
   - Upload the **`public-php/`** folder contents directly into `htdocs/`
     (so `config.php` lands at `htdocs/config.php` and endpoints at
     `htdocs/api/audio.php`, etc.).
4. **phpMyAdmin** → select your DB → SQL tab → paste the full
   `public-php/schema.sql` → Go.
5. Open `config.php` **first** and set:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (from step 2)
   - `ALLOW_ORIGIN` = your InfinityFree domain
   - `SUPABASE_ANON_KEY` (from your Supabase project settings)
   - `JAMENDO_CLIENT_ID` (free at <https://dev.jamendo.com/en/accounts/register/>)
6. Test: open `https://yoursite/api/trending.php` in browser → should
   show `{"tags":[],"generated_at":"..."}` (empty until cron runs once).

## Cron (for trending)

cPanel → **Cron Jobs** → add:
```
Command:  curl -s "https://yoursite/api/recompute_trending.php?key=YOUR_LONG_RANDOM_KEY"
Schedule: Every 30 minutes
```
Edit `CRON_SECRET` in `recompute_trending.php` to match.

## How the frontend uses this

Add to `index.html` (or wherever your composer is):

```js
// Trending chips (call once per app open)
const trending = await fetch('https://yoursite/api/trending.php').then(r => r.json());
// trending.tags = [{tag:'anxious', count_24h:42}, ...]

// Audio selector
async function searchAudio(q) {
  const r = await fetch(`https://yoursite/api/audio.php?q=${encodeURIComponent(q)}`);
  return (await r.json()).tracks;
}
async function pinTrack(jamendoId) {
  const token = (await supabase.auth.getSession()).data.session.access_token;
  const r = await fetch(`https://yoursite/api/audio.php?id=${jamendoId}&save=1`, {
    headers: { Authorization: 'Bearer ' + token },
  });
  return (await r.json()).id;   // audio_library.id
}
```

The **track URL is a 30-second preview mp3** (mp32 format). Attach to a
post by saving `url`, `title`, `artist`, `attribution` into your
existing Supabase `posts` columns (`music_url`, `music_name`, etc.).

## What "as smooth as IG/FB" actually needs (frontend side)

Ang PHP layer ay hindi nagpapa-smooth ng scroll. Ang smoothness ay
galing sa **frontend patterns** — mga ito ang susunod nating gawin sa
`index.html` (priority order):

1. **Optimistic UI** — i-render agad ang like/comment/chat bago pa
   bumalik ang Supabase. (5–10 lines per action.)
2. **Skeleton loaders** — gray placeholder cards habang naglo-load.
3. **Virtualized feed** — i-unmount ang mga post na wala sa viewport
   (pinaka-malaking smoothness gain pag 500+ posts).
4. **Pull-to-refresh** + infinite scroll na walang jank.
5. **Image lazy-load** + blur-up (using Supabase's `image` transforms).
6. **@mentions + hashtags autocomplete** sa composer (mga trending
   chips from `/api/trending.php`).
7. **Story viewer with tap/swipe** (IG-style) — may `myday_stories` ka
   na pero kailangan pa ng gestures.
8. **Message composer** na parang IG: quick reactions, typing indicator
   (Supabase presence), reply-to, unread badge.
9. **Tab persistence** — buhayin ang scroll position ng feed/messenger
   pag bumalik ka sa tab.
10. **Dark mode toggle** + safe-space theme presets.

Kung gusto mo, sabihin mo lang **alin sa 1–10 ang unahin natin** sa
susunod na round.

## APK (walang URL bar sa taas)

Yung current `ANDROID-APK-GUIDE.txt` mo = **WebView** wrapper, kaya
nakikita ang URL. Ang tamang solusyon:

- **Trusted Web Activity (TWA)** → native shell, **walang address bar**.
  - Nangangailangan ng **custom domain** na may `/.well-known/assetlinks.json`
    file. Hindi gumagana sa libreng subdomain.
  - Free custom-domain workarounds: Cloudflare Pages (may `*.pages.dev`
    TWA support), o bilhin ang domain ($2–5/year).
- Alternative: **PWA install** — kung ang user ay mag-i-install sa
  Home Screen sa mobile browser, tatakbong **standalone mode** ang app
  (walang URL bar). Simple lang: `manifest.json` → `"display":"standalone"`
  + `sw.js`. Mayroon ka na, tingnan mo lang kung tama.

## Notes / honest limitations

- **Push notifications** (VAPID) — mananatiling **Supabase Edge Function**
  ang sender, hindi PHP (blocked ang outbound HTTP sa free PHP hosts).
- **Realtime chat** — mananatiling **Supabase Realtime**. Walang websocket
  sa PHP host, kaya huwag nating alisin ang Supabase realtime.
- Kung ever lumipat ka sa paid VPS, lahat ng PHP endpoints dito ay
  gumagana pa rin nang walang change.

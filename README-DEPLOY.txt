LITANYA SAFESPACE - DEPLOY PACKAGE
==================================

WHAT'S INSIDE
1. index.html            -> the whole website (HTML + CSS + JS in one file).
2. supabase-schema.sql   -> database setup (tables, security rules, storage
                            buckets, realtime, notification triggers).
3. manifest.json         -> PWA app manifest (makes the site installable).
4. sw.js                 -> service worker (offline app shell + instant load).
5. icon-192.png, icon-512.png, icon-maskable-512.png, apple-touch-icon.png,
   favicon-32.png        -> app icons used when installed / packaged as APK.
6. ANDROID-APK-GUIDE.txt -> how to turn this website into an Android APK.
7. README-DEPLOY.txt     -> this file.

DEPLOY THE WEBSITE (Cloudflare - free)
1. Cloudflare dashboard -> Workers & Pages -> Create -> Pages -> "Upload assets".
2. Drag the whole unzipped folder in (index.html + manifest.json + sw.js +
   the icon .png files together, all at the root).
3. Deploy. Your site goes live on a free *.pages.dev link (custom domain optional).

SETUP THE DATABASE (Supabase)
1. supabase.com dashboard -> your project -> SQL Editor -> New query.
2. Paste the whole supabase-schema.sql and click Run. It is safe to re-run:
   it only adds what is missing and never deletes users, posts or chats.
3. Authentication -> Sign In / Providers -> Email -> turn OFF "Confirm email".
   (The app uses usernames only - no real emails exist. Leaving it ON causes
   "too many attempts" sign-up errors because Supabase blocks the mail flood.)
4. Optional: Authentication -> Rate Limits -> raise sign-up / sign-in limits
   if the app will get many new users at once.

NOTES
- The Supabase URL and public key are already inside index.html; nothing to configure.
- NEW: the site is now an installable PWA. On Android (Chrome), open the site
  -> More menu -> "Install app". It gets a home-screen icon, splash screen and
  runs full-screen like a native app, with an offline shell for weak signal.
- NEW: to build the actual Android APK, follow ANDROID-APK-GUIDE.txt (free,
  browser-only, using PWABuilder - no Android Studio needed).
- Existing accounts keep working: open the site on the device where the old
  account lives and tap "Add password" on the banner - all posts, MyDays,
  chats and friends stay under the same account.
- After signing in or signing up, users now land directly on the main page
  (Freedom Wall feed).
- New-in-this-build interactions: double-tap a post image to like it with a
  heart burst, pull down to refresh feeds, native share sheet, haptic feedback,
  and an Instagram-style Messenger (gradient bubbles, unread dots, heart
  reactions on messages, swipe/right-click to reply).

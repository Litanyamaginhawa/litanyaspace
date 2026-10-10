# LitanyaSpace Push Notification Kit

## Files
- `sw.js` — browser push service worker. It must be reachable at:
  `https://litanyaspace.litanya.workers.dev/sw.js`
- `manifest.json` — PWA metadata. The site may link it using:
  `<link rel="manifest" href="/manifest.json">`
- `supabase/functions/send-post-push/index.ts` — Supabase Edge Function source.
- `trigger-template.sql` — optional SQL template for the database trigger.

## Important: keep the VAPID keys matched
1. The VAPID public key in `index.html` must exactly match the Supabase secret `VAPID_PUBLIC_KEY`.
2. The matching private key goes only in Supabase secrets as `VAPID_PRIVATE_KEY`.
3. Never commit the private key, service-role key, or webhook secret to GitHub.
4. Existing subscriptions created with an older VAPID key pair may fail with a VAPID mismatch. After the matching public key is live, users may need to disable/re-enable notifications so the browser creates a fresh subscription.

## Supabase Edge Function secrets
Set these in Supabase Dashboard → Edge Functions → Secrets:
- `SUPABASE_URL` = `https://gmjtpvxwymevjsicbwlh.supabase.co`
- `SUPABASE_SERVICE_ROLE_KEY` = the project's service-role key (keep private)
- `VAPID_PUBLIC_KEY` = exactly the public key in the deployed `index.html`
- `VAPID_PRIVATE_KEY` = private key paired with that public key
- `PUSH_WEBHOOK_SECRET` = a new long random secret, same value used in the SQL trigger header

Deploy the function named `send-post-push`.

## Static site
Put `sw.js` and `manifest.json` in the same static root as `index.html`.
After deployment, check that opening `/sw.js` shows JavaScript and `/manifest.json` shows JSON, not a 404 or the HTML page.

## Existing setup warning
The SQL file is a template, not something to run blindly. If your existing trigger already calls `send-post-push`, keep it and only ensure its header secret matches `PUSH_WEBHOOK_SECRET`. Running a second trigger could send duplicate notifications.

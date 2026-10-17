# Litanya Safespace — Closed-App Push (messages + comments)

This makes **real push notifications** fire even when the PWA is fully **closed**,
for messages, comments, reactions and MyDay replies (not just posts).

You only need the **Supabase dashboard** + a one-time **Supabase CLI** step to
deploy the function. Do it top to bottom.

---

## 1. Deploy the Edge Function (once)

On a computer with [Node.js](https://nodejs.org) installed, in a terminal:

```bash
npm i -g supabase
supabase login
# create a local project link to your ref gmjtpvxwymevjsicbwlh
supabase link --project-ref gmjtpvxwymevjsicbwlh

# put send-notification-push.ts at  supabase/functions/send-notification-push/index.ts
# then:
supabase functions deploy send-notification-push --no-verify-jwt
```

### Set the secrets the function needs

> Fresh VAPID keypair generated for this project (the old private key was lost).
> Because the key rotated, every user must **re-enable notifications once** — the
> app now auto-detects the stale subscription and re-subscribes.

```bash
supabase secrets set \
  VAPID_PUBLIC_KEY=BDvV_N9w9MWNttQ31sWwtFpgPIqcwffLAmBpKTA5yRvy9kyqLrGJgP2w4_1P88b3l3MOkxIbYv6lfUIpLoe2z20 \
  VAPID_PRIVATE_KEY=pA_4V-mIwm0e3dTK499nd13wA-JIqSNNavX9MehK8WE \
  VAPID_SUBJECT=mailto:admin@litanyaspace.app \
  PUSH_TRIGGER_SECRET=<a long random string you invent>
```

> **Also update `send-post-push`** with the SAME new `VAPID_PUBLIC_KEY` +
> `VAPID_PRIVATE_KEY`, otherwise post-push will use the old (dead) key.

> You can also set these without CLI: **Dashboard → Project → Edge Functions →
> Manage secrets → New secret**.

---

## 2. Add the DB trigger (no CLI)

1. Supabase **Dashboard → SQL Editor → New query**
2. Paste all of **`trigger.sql`**
3. **Replace** `REPLACE_WITH_YOUR_LONG_RANDOM_SECRET` with the **exact same**
   `PUSH_TRIGGER_SECRET` you set above
4. Click **Run**

---

## 3. Test

- Log in on your phone, open the app, tap **Enable notifications**
- Put the app in another account, close the PWA entirely
- Have a **second account send you a message**
- ✅ A system notification should appear; tapping it opens **/messenger**

---

## How it fits the current build

| Situation | Handled by | Status |
|---|---|---|
| App open / backgrounded tab | Supabase **realtime** → `__litanyaNotify` (v2.8) | ✅ LIVE |
| App fully closed (posts) | existing `send-post-push` | ✅ already working |
| App fully closed (messages/comments) | **this** `send-notification-push` + trigger | ⬅️ setup here |

No front-end change is needed — the `sw.js` already has the `push` +
`notificationclick` handlers that display and route these pushes.

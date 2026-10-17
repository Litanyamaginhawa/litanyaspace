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

```bash
supabase secrets set \
  VAPID_PUBLIC_KEY=BGJEZXi2F4sdybATBwPJTaTEIm0lLIKmlRER94k0hjKbD94FIkEhK4p0YuqektlaeXIThjyrvAgYOePYqvK5CxI \
  VAPID_PRIVATE_KEY=<same private key already used by send-post-push> \
  VAPID_SUBJECT=mailto:admin@litanyaspace.app \
  PUSH_TRIGGER_SECRET=<a long random string you invent>
```

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

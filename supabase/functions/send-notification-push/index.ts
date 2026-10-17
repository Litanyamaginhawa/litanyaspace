// ============================================================
// Litanya Safespace — Notification Push Edge Function
// Sends VAPID web-push for ANY row added to the `notifications`
// table (message / comment / reaction / myday_reply), so users
// get alerts even when the app is fully closed.
//
// Deploy (from a machine WITH the Supabase CLI):
//   supabase functions deploy send-notification-push --no-verify-jwt
//
// Required Function Secrets (reuse the same ones send-post-push uses):
//   VAPID_PUBLIC_KEY   = BAJGSIM-ksVEAqRaZAsKt8N01abzkImq5RH2TT3OxtbTzBHUUR_OATrCRzGOjMVN9V7DGYi7Frxlb9BS0_PoCQY
//   VAPID_PRIVATE_KEY  = (paste the matching private key from vapid-keys.txt)
//   VAPID_SUBJECT      = mailto:admin@litanyaspace.app
//   PUSH_TRIGGER_SECRET= (any long random string; MUST match the SQL trigger header)
//   SUPABASE_URL / SUPABASE_SERVICE_ROLE_KEY are provided automatically by Supabase.
// ============================================================

import webpush from "npm:web-push@3.6.7";

const VAPID_PUBLIC = Deno.env.get("VAPID_PUBLIC_KEY") || "";
const VAPID_PRIVATE = Deno.env.get("VAPID_PRIVATE_KEY") || "";
const VAPID_SUBJECT = Deno.env.get("VAPID_SUBJECT") || "mailto:admin@litanyaspace.app";
const TRIGGER_SECRET = Deno.env.get("PUSH_TRIGGER_SECRET") || "";
const SUPABASE_URL = Deno.env.get("SUPABASE_URL") || "";
const SERVICE_KEY =
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") || Deno.env.get("SUPABASE_ANON_KEY") || "";

webpush.setVapidDetails(VAPID_SUBJECT, VAPID_PUBLIC, VAPID_PRIVATE);

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type, x-push-secret",
};

// Turn a notification row into a tap-target route inside the PWA.
function routeFor(type: string): string {
  if (type === "message") return "/messenger";
  if (type === "comment" || type === "comment_reply") return "/wall";
  if (type === "myday_reply") return "/myday";
  return "/notifications";
}

Deno.serve(async (req: Request) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });

  try {
    // Shared-secret gate: only our DB trigger (or dashboard webhook) may invoke this.
    if (TRIGGER_SECRET) {
      const got = req.headers.get("x-push-secret") || "";
      if (got !== TRIGGER_SECRET) {
        return new Response(JSON.stringify({ error: "forbidden" }),
          { status: 403, headers: { ...corsHeaders, "Content-Type": "application/json" } });
      }
    }

    const parsed = await req.json().catch(() => ({} as any));
    // Accept BOTH a raw notification row AND the Supabase webhook envelope { record: {...} }.
    const rec: any = parsed.record || parsed;

    const recipient = rec.user_id || rec.recipient_id;
    if (!recipient) {
      return new Response(JSON.stringify({ skipped: "no recipient" }),
        { status: 200, headers: { ...corsHeaders, "Content-Type": "application/json" } });
    }

    const title = String(rec.title || "Litanya Safespace");
    const body = String(rec.body || "May bagong update ka.");
    const url = routeFor(String(rec.type || ""));

    // Fetch every push subscription for this recipient.
    const res = await fetch(
      `${SUPABASE_URL}/rest/v1/push_subscriptions?user_id=eq.${recipient}&select=endpoint,p256dh,auth`,
      { headers: { apikey: SERVICE_KEY, Authorization: `Bearer ${SERVICE_KEY}` } },
    );
    const subs = await res.json();
    if (!Array.isArray(subs) || subs.length === 0) {
      return new Response(JSON.stringify({ sent: 0, note: "no subscriptions" }),
        { status: 200, headers: { ...corsHeaders, "Content-Type": "application/json" } });
    }

    const payload = JSON.stringify({ title, body, url, icon: "/apple-touch-icon.png" });
    let sent = 0, failed = 0;
    await Promise.allSettled(
      subs.map(async (s: any) => {
        try {
          await webpush.sendNotification(
            {
              endpoint: s.endpoint,
              keys: { p256dh: s.p256dh, auth: s.auth },
            },
            payload,
          );
          sent++;
        } catch (_e) {
          failed++; // stale/expired subscription — client will re-subscribe on next open.
        }
      }),
    );

    return new Response(JSON.stringify({ sent, failed }),
      { status: 200, headers: { ...corsHeaders, "Content-Type": "application/json" } });
  } catch (err: any) {
    return new Response(JSON.stringify({ error: String(err?.message || err) }),
      { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } });
  }
});

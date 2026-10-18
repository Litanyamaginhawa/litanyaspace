// secure-account — Edge Function
// Lets a signed-in (temporary/anonymous) account attach its internal login
// email (e.g. @litanya.app) + a password WITHOUT GoTrue's email-change flow.
//
// Why: client.auth.updateUser({email}) triggers (a) MX/deliverability checks
// that reject our internal fake domains, and (b) confirmation emails that hit
// the free-tier rate limit (2/min). The admin API skips both when
// email_confirm:true, and no email is ever sent.
//
// Security:
// - Deployed with verify_jwt ON, so only requests carrying a valid user JWT
//   reach it. The function derives the target user id from the CALLER's own
//   token (via GET /auth/v1/user) — it can never touch someone else's account.
// - The password is forwarded once over TLS to the admin API; nothing stored.
//
// Invoke:  POST /functions/v1/secure-account
//          Authorization: Bearer <user JWT>
//          { "email": "name@litanya.app", "password": "..." }
// Reply:   { "ok": true, "id": "..." } or { "ok": false, "error": "..." }

const SUPABASE_URL = Deno.env.get("SUPABASE_URL") ?? "";
const SERVICE_KEY = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "";

const CORS: Record<string, string> = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, content-type, apikey",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};

function json(obj: unknown, status = 200) {
  return new Response(JSON.stringify(obj), {
    status,
    headers: { "Content-Type": "application/json", ...CORS },
  });
}

Deno.serve(async (req: Request) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: CORS });
  if (req.method !== "POST") return json({ ok: false, error: "method not allowed" }, 405);
  if (!SUPABASE_URL || !SERVICE_KEY) return json({ ok: false, error: "function env not configured" }, 500);

  const userToken = (req.headers.get("Authorization") || "").replace(/^Bearer\s+/i, "").trim();
  if (!userToken) return json({ ok: false, error: "missing user token" }, 401);

  let email = "", password = "";
  try {
    const body = await req.json();
    email = String(body?.email || "").trim().toLowerCase();
    password = String(body?.password || "");
  } catch {
    return json({ ok: false, error: "invalid JSON body" }, 400);
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return json({ ok: false, error: "invalid email format" }, 400);
  if (password.length < 8) return json({ ok: false, error: "password too short" }, 400);

  // Resolve the CALLER's own id from their JWT (never accept an id from the body).
  const meRes = await fetch(`${SUPABASE_URL}/auth/v1/user`, {
    headers: { apikey: SERVICE_KEY, Authorization: `Bearer ${userToken}` },
  });
  if (!meRes.ok) return json({ ok: false, error: "invalid or expired session" }, 401);
  const me = await meRes.json();
  if (!me?.id) return json({ ok: false, error: "no user for this session" }, 401);

  // Admin update: set email + password, mark confirmed, keep metadata.
  const upd = await fetch(`${SUPABASE_URL}/auth/v1/admin/users/${me.id}`, {
    method: "PUT",
    headers: { apikey: SERVICE_KEY, Authorization: `Bearer ${SERVICE_KEY}`, "Content-Type": "application/json" },
    body: JSON.stringify({ email, password, email_confirm: true }),
  });
  if (!upd.ok) {
    const detail = await upd.text().catch(() => "");
    return json({ ok: false, error: `admin update failed (${upd.status}): ${detail.slice(0, 300)}` }, 502);
  }
  return json({ ok: true, id: me.id });
});

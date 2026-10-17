# LitanyaSpace — ANDROID APK (TWA) UPDATE GUIDE

This folder + file is the single source of truth for shipping/updating the
Android app. **Everything here is LOCAL only — never push this folder to GitHub.**

---

## 1) App identity
- Live site / PWA origin: `https://litanyaspace.litanya.workers.dev/`
- GitHub repo (web root = repo root): `Litanyamaginhawa/litanyaspace` (branch `main`)
- Supabase project ref: `gmjtpvxwymevjsicbwlh`
- **Android package (applicationId): `com.litanyaspace.app`**  ← must never change
- App display name: `Litanya Safespace`  ·  Short name: `LitanyaSpace`

## 2) Signing assets (KEEP FOREVER — losing these = cannot update the app cleanly)
- `signing.keystore`  — the certificate that signs the APK.
- `signing-key-info.txt` — keystore password + alias (`my-key-alias`). **This is the secret; keep it safe, do not commit.**
- **SHA-256 cert fingerprint:**
  `D3:6C:A9:E9:76:62:15:CE:00:EE:8C:4F:EB:02:7B:65:1F:41:2F:C6:A0:D2:7C:A9:56:9B:A6:83:A0:F3:49:03`
- `assetlinks.json` — the file that gives the **"no URL bar"** full-screen mode.
  It is LIVE at: `https://litanyaspace.litanya.workers.dev/.well-known/assetlinks.json`
  (repo path `.well-known/assetlinks.json`). Source copy: `deploy\.well-known\assetlinks.json`.

## 3) Files to ship
- `LitanyaSpace.apk` — give THIS to users to install (sideload / share). (~3 MB, correctly signed)
- `LitanyaSpace.aab` — use for Google Play upload.
- AVOID the old `Downloads\_MConverter.eu_*_Android_Project.apk` files — broken, wrong signature.

---

## 4) HOW TO DO THE NEXT UPDATE (the correct way)
The web app itself updates by pushing to GitHub (auto-deploys via Cloudflare).
**You usually do NOT need a new APK for content changes** — the APK is just a
thin TWA wrapper that loads the live site, so pushing to GitHub already updates
what users see (including new features + notifications).

Build a new APK only if: the package name, icons, app name, splash, or deep-link
config changes, or you changed the manifest in a way the installed wrapper caches.

Steps to rebuild the APK:
1. PWABuilder.com → paste `https://litanyaspace.litanya.workers.dev/` → Package for stores → **Android**.
2. App name `Litanya Safespace`, package **`com.litanyaspace.app`** (unchanged).
3. **Signing: choose "Upload an existing keystore"** and use the `signing.keystore`
   in this folder (alias `my-key-alias`, password in `signing-key-info.txt`).
   ⚠️ **Do NOT pick "generate new keystore"** — a new key = new fingerprint =
   the URL bar COMES BACK and Play/user installs break.
4. Build → download zip → this is your new `LitanyaSpace.apk` / `.aab`.

## 5) If the signing key EVER changes (or you enable Play App Signing)
- Get the new SHA-256 fingerprint and ADD it to the `sha256_cert_fingerprints`
  array in `deploy\.well-known\assetlinks.json` (it accepts multiple values).
- Re-push with `push-assetlinks.ps1` and confirm it still returns
  `application/json` at the `/.well-known/assetlinks.json` URL.

## 6) One-time cost of a VAPID key rotation (push notifications)
If the push VAPID keys are rotated (see root `vapid-keys.txt` + memory), every
user taps "Enable notifications" once; the app auto re-subscribes. Does not
affect the APK itself.

---

## 7) Helper scripts in the workspace root
- `push-v22.ps1`            — commits `deploy\index.html` + `deploy\sw.js` to GitHub.
- `push-assetlinks.ps1`     — pushes `deploy\.well-known\assetlinks.json` to repo `/.well-known/`.
- `push-push-artifacts.ps1` — pushes the Supabase push Edge Function + docs to `/supabase/`.
- `sb-set-secrets.ps1`      — sets VAPID secrets in Supabase (uses token in Downloads, not committed).
- `gen-vapid.ps1` / `vapid-keys.txt` — P-256 keypair generator + current pair (LOCAL, secret).

## 8) Notes / gotchas
- Cloudflare serves `sw.js` from its own cache; it may lag the repo by ~1 min after a push.
- `assetlinks.json` must be plain HTTPS JSON with no SPA fallback (verified working on `*.workers.dev`).
- Android caches Digital Asset Links; a fresh install can take a moment before full-screen trust kicks in.
- iOS has no APK — "Add to Home Screen" gives the same no-URL-bar standalone mode (A2HS banner is built in).

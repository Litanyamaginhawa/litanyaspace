# 📦 LitanyaSpace Deploy Kit

Eto ang **tapat na folder na ii-upload mo** sa hosting provider mo (InfinityFree, Cloudflare Pages, Vercel, o kahit ano). Lahat ng kailangan ng app ay nandito na, plus ang injected v2.1 smoothness patches sa `index.html`.

## Ano ang bago sa `index.html` (v2.1)

Idinagdag sa **dulo ng `<style>`** at sa **bago ang `</body>`**:

1. **Global smooth transitions** sa cards, buttons, nav, msgs
2. **Offscreen virtualization** via `content-visibility: auto` sa `postbody` images/videos
   → browser na mismo ang nag-skip ng painting ng mga post na wala sa screen. Pinaka-malaking smoothness gain.
3. **Scroll persistence** — kapag nagpalit ka ng tab (Wall → Reels → Wall), **nandoon ka pa rin** sa huli mong napuntahan. Parang FB/IG.
4. **Image lazy-load** automatic sa lahat ng `<img>` na walang `loading` attribute.
5. **`prefers-reduced-motion`** respect — para sa users na may motion sensitivity.
6. **`window.__litanyaFlash(msg)`** — global optimistic-flash helper; pwede mong tawagin anumang place.

**Wala akong binago** na existing function. Ang lahat ng existing features (dark mode, pull-to-refresh, skeletons, reactions, MyDay, stories, chat, push) ay **buhay pa rin**.

Kung ever gusto mong i-undo: hanapin ang "LitanyaSpace v2.1" comment blocks at tanggalin ang 2 sections.

## Paano i-deploy

### Option A — **InfinityFree** (PHP+MySQL host, libre)
1. Sign up sa InfinityFree → create account/site → **custom domain** (recommended) o free subdomain.
2. **cPanel → File Manager → `htdocs/`** → **i-upload lahat ng file mula sa `deploy/`** folder na ito.
   - `index.html`, `sw.js`, `manifest.json`, icons → root ng `htdocs/`
   - Kung may `public-php/` ka rin (trending + audio endpoints), i-upload yung **laman** ng `public-php/` (hindi yung folder name) sa `htdocs/`. So havi ka with `htdocs/config.php`, `htdocs/api/audio.php`, atbp.
3. Kung gagamitin mo yung PHP layer: **cPanel → MySQL Databases** → create DB + user, then phpMyAdmin → SQL tab → paste `../public-php/schema.sql`.
4. Buksan ang `config.php` at palitan ang DB_HOST, DB_NAME, DB_USER, DB_PASS, ALLOW_ORIGIN, SUPABASE_ANON_KEY, JAMENDO_CLIENT_ID.
5. Test: buksan ang `https://yoursite/api/trending.php` sa browser.

### Option B — **Cloudflare Pages** (pinakamabilis, pinakamadaling libreng static host)
1. Cloudflare dashboard → **Pages** → **Create project** → **Upload assets** (drag-drop).
2. I-drag ang **lahat ng file sa `deploy/`** folder.
3. Deploy → auto-HTTPS, auto CDN, walang ads. URL: `your-project.pages.dev`.
4. (Optional) Connect your GitHub repo → auto-deploy sa bawat commit.

Kung ito ang pipiliin mo: **i-delete mo na lang yung `public-php/` folder**, dahil hindi tumatakbo ang PHP sa Cloudflare Pages. Pero ang core app (posts, chat, push, stories) ay **gumagana pa rin** dahil puro Supabase yan.

### Option C — **Vercel** (ganyan din sa Pages, may free tier)
1. `npm i -g vercel` (o sign in sa vercel.com → Import Project → drag `deploy/` folder).
2. Auto-deploy, auto-HTTPS. Walang PHP support sa free tier.

## 🎯 APK (walang URL bar sa taas)

Tinitingnan ko yung `ANDROID-APK-GUIDE.txt` mo — **WebView-based** yan, kaya nakikita ang URL bar. Ang PWA setup mo (`manifest.json` + `sw.js` + HTTPS) ay **already complete** — so **hindi mo na kailangan ng custom APK**.

### Pinakamadaling solusyon (rekomendado):
**"Add to Home Screen"** sa Chrome/Safari → **standalone mode** ang app, **walang URL bar**. Parang natukoy mong "like a real app."

- Android: Chrome → open site → ⋮ → **Add to Home screen**. Tatakbong native window, walang chrome.
- iOS: Safari → Share → **Add to Home Screen**.

### Kung kailangan-talaga ng `.apk` file:
Gamitin ang **[PWABuilder.com](https://www	pwabuilder.com/)** (official, free, from Microsoft):
1. Pumunta sa PWABuilder → i-paste ang URL ng deployed site mo.
2. Pindutin **Package for stores** → **Android**.
3. I-download ang **signed APK** (Trusted Web Activity / bubblewrap output).
4. **Walang URL bar.** Real native app experience.

**Hindi** ito gumagana sa free subdomains (`*.infinityfreeapp.com`). Kailangan mo ng **custom domain** (o kaya Cloudflare Pages default `*.pages.dev`, na **supported** ng TWA).

Kaya **kung APK ang final goal mo, pipiliin ko Option B (Cloudflare Pages)** — pinakamabilis, TWA-compatible, walang bayad.

## Testing checklist (before you say "okay")

- [ ] Ma-open ang site sa phone (HTTPS).
- [ ] Pwede mag-sign in, gumawa ng post, mag-react, mag-comment.
- [ ] **Smoothness check**: mag-scroll ng feed nang mabilis — dapat walang jank sa middle/lower posts.
- [ ] **Scroll persistence**: pumunta sa Reels tab, bumalik sa Wall — dapat nandoon ka pa rin sa huli mong scroll.
- [ ] **Dark mode** toggle works (may existing `html[data-theme="dark"]`).
- [ ] **Pull-to-refresh** may indicator na.
- [ ] **Install PWA**: Add to Home Screen → buksan → **dapat walang URL bar**.
- [ ] **Push notifications**: Opt-in → gumawa ng post from another account → dapat may push.

## Kung sakaling may problema
- **Blank screen**: Check browser DevTools Console. Ang `LitanyaSpace v2.1` block ay additive lang — kung error, i-delete mo yung dalawang blocks (sa `</style>` at bago `</body>`).
- **Images hindi nag-lazy**: `content-visibility` needs Chrome 85+ / Safari 17+. Sa mababagong Android (pre-2020), okay lang — wala lang yung virtualization boost.
- **Push hindi sumasagot**: Iyan ay **hindi frontend issue** — ang sender ay Supabase Edge Function. Checkin mo ang VAPID keys sa dashboard.

## Bottom line
**Ang PHP layer (trending/audio) ay OPTIONAL add-on.** Kung **smooth + APK** ang priority mo, **i-deploy lang ang `deploy/` folder sa Cloudflare Pages** (Option B) — **3 minutes** lang, tapos **Add-to-Home-Screen** na lang para sa users, walang APK build na kailangan.

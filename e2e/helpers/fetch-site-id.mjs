// Reads site_id from the store's localized plugin params via HTTP. Runs as a subprocess of
// site-guard.js (execFileSync) to keep skipIfNotSite synchronous. Prints the site_id to
// stdout; prints nothing if not detected so the caller treats empty string as "unknown".
const url = process.argv[2];
if (!url) process.exit(0);

const base = url.replace(/\/+$/, "");
const controller = new AbortController();
const timer = setTimeout(() => controller.abort(), 15000);

try {
  // /checkout/ with empty cart redirects to /cart/, but the MP localized params (with site_id)
  // are already enqueued in that response — no session or cart needed.
  const res = await fetch(base + "/checkout/", { redirect: "follow", signal: controller.signal });
  const html = await res.text();
  const match = html.match(/"site_id":"([A-Za-z]{3})"/);
  if (match) process.stdout.write(match[1].toUpperCase());
} catch {
  // Caller treats empty stdout as "not detected".
} finally {
  clearTimeout(timer);
}

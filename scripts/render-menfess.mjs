import { chromium } from 'playwright';

let input = '';
for await (const chunk of process.stdin) input += chunk;

const data = JSON.parse(input);
const safeText = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
}[character]));
const safeColor = (value, fallback) => /^#[0-9a-fA-F]{6}$/.test(String(value ?? '')) ? value : fallback;
const width = Number(data.width);
const height = Number(data.height);
if (![1080, 1200].includes(width) || ![675, 1080, 1350].includes(height)) throw new Error('Ukuran kanvas tidak didukung.');

const background = safeColor(data.theme?.background, '#F6DDE8');
const accent = safeColor(data.theme?.accent, '#DCEBFA');
const badgeColor = safeColor(data.category_color, '#DCEBFA');
const imageTag = data.photo_data_uri
    ? `<img class="photo" src="${data.photo_data_uri}" alt="Foto kiriman">`
    : '';
const html = `<!doctype html><html lang="id"><head><meta charset="utf-8"><style>
    *{box-sizing:border-box}html,body{margin:0;width:${width}px;height:${height}px}body{font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:${background};color:#182a4b;padding:54px;display:flex;flex-direction:column}
    .header{display:flex;align-items:center;gap:18px;min-height:72px}.logo{width:64px;height:64px;border-radius:22px;background:#fff;display:grid;place-items:center;color:#c96c91;font-size:34px;font-weight:800}.brand{font-size:22px;font-weight:800;letter-spacing:-.03em}.meta{margin-top:5px;font-size:15px;color:#5d6a82}.card{position:relative;flex:1;margin:38px 0 28px;padding:52px 48px;border-radius:42px;background:#fff;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 18px 48px #182a4b12}.quote{position:absolute;top:18px;left:31px;font-family:Georgia,serif;font-size:100px;line-height:1;color:${accent};opacity:.85}.badge{align-self:flex-start;z-index:1;border-radius:999px;background:${badgeColor};padding:11px 20px;font-size:17px;font-weight:800}.photo{width:100%;height:42%;object-fit:cover;border-radius:26px;margin-top:30px;background:#f5f5f5}.body{z-index:1;white-space:pre-wrap;overflow-wrap:anywhere;word-break:normal;font-weight:650;line-height:1.42;letter-spacing:-.025em;margin-top:36px;flex:1;min-height:0}.number{text-align:right;color:#8a94a5;font-size:16px;font-weight:700;margin-top:24px}.footer{display:flex;align-items:center;justify-content:space-between;color:#58657e;font-size:16px;font-weight:650}.footer .social{color:#182a4b}.photo-mode .body{flex:0;font-size:28px;margin-top:26px}.photo-mode .card{padding-top:44px}.photo-mode .photo{height:50%;}
</style></head><body class="${data.photo_data_uri ? 'photo-mode' : ''}"><header class="header"><div class="logo">✳</div><div><div class="brand">${safeText(data.brand_name || 'Ruang Kirim')}</div><div class="meta">${safeText(data.username || '@ruangkirim')} · ${safeText(data.date_label)}</div></div></header><main class="card"><div class="quote">“</div><span class="badge">${safeText(data.category_name || 'umum!')}</span>${imageTag}<div id="body" class="body">${safeText(data.body)}</div><div class="number">${safeText(data.public_number || 'PRATINJAU')}</div></main><footer class="footer"><span>${safeText(data.domain || 'ruangkirim.id')}</span><span class="social">${safeText(data.username || '@ruangkirim')}</span></footer><script>
const body=document.getElementById('body');let size=42;body.style.fontSize=size+'px';while(size>24&&body.scrollHeight>body.clientHeight){size-=1;body.style.fontSize=size+'px'}if(body.scrollHeight>body.clientHeight)document.body.dataset.overflow='true';
</script></body></html>`;

const browser = await chromium.launch({ headless: true });
try {
    const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 1 });
    await page.setContent(html, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    if (await page.locator('body').getAttribute('data-overflow')) throw new Error('Teks terlalu panjang untuk satu slide; pendekkan pesan atau gunakan pemisahan slide.');
    await page.screenshot({ path: data.output_path, type: 'png', fullPage: false });
} finally {
    await browser.close();
}

// Снимки всех кадров канвы Claude Design.
// Использование:  node shots.mjs <канва.dc.html> [папка-вывода] [префикс id, например 3]
// Пример:         node shots.mjs docs/design/session-canvas.dc.html shots 3
// Один раз:       npm i -D playwright && npx playwright install chromium
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import path from 'node:path';
import fs from 'node:fs';

const [,, file, outDir = 'shots', prefix = ''] = process.argv;
if (!file) { console.error('нужен путь к .dc.html'); process.exit(1); }

fs.rmSync(outDir, { recursive: true, force: true });
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1600, height: 1200 }, deviceScaleFactor: 2 });
// networkidle упирался в Google Fonts: если CDN тормозит или сети нет, goto падает по 30 с.
// Ждём только документ, шрифты — отдельно и с потолком.
await page.goto('file://' + path.resolve(file), { waitUntil: 'load', timeout: 60000 });
await page.evaluate(() => Promise.race([document.fonts.ready, new Promise(r => setTimeout(r, 5000))]));
await page.waitForTimeout(500); // картинки
// бесконечные анимации канвы (om-wave, om-lift, om-pulse…) не дают кадру «успокоиться» — screenshot ждёт 30 с и падает.
// Показываем конечное состояние: анимации и переходы выключены.
await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });

// кадр = div с id вида 21-2 / 23-0a / 30-9; снимаем блок кадра целиком, включая полоску состояний и «Вычтено»
const ids = await page.$$eval('div[id]', els =>
  els.map(e => e.id).filter(id => /^\d{2}-\d+[a-z]?$/.test(id)));

const wanted = prefix ? ids.filter(id => id.startsWith(prefix)) : ids;
console.log(`кадров: ${wanted.length}`);

for (const id of wanted) {
  const el = await page.$(`div[id="${id}"]`);
  if (!el) continue;
  await el.scrollIntoViewIfNeeded();
  await page.waitForTimeout(150);
  // блок кадра = сам div[id] или ближайший предок, где есть «Вычтено», но нет чужих кадров
  // (в session-canvas номер, полоска состояний и «Вычтено» лежат внутри div[id]; section — это весь раздел)
  const target = await el.evaluateHandle(e => {
    const isFrame = n => /^\d{2}-\d+[a-z]?$/.test(n.id);
    for (let n = e; n && n !== document.body; n = n.parentElement) {
      if (n !== e && [...n.querySelectorAll('div[id]')].some(d => d !== e && isFrame(d))) break;
      if (n.textContent.includes('Вычтено')) return n;
    }
    return e;
  });
  await target.asElement().screenshot({ path: path.join(outDir, `${id}.png`), animations: 'disabled', caret: 'hide', timeout: 60000 });
  console.log('  ', id);
}
await browser.close();

const zip = `${outDir}.zip`;
fs.rmSync(zip, { force: true });
execSync(`zip -qr ${zip} ${outDir}`);
console.log('готово:', zip);

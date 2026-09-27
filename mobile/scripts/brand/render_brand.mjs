// BRAND ASSETS FROM THE BRAND CANVAS — the wordmark, the launch «R» and the 1024 app icon (work order CLIENT-START §1).
//
// Why a browser and not Flutter: the canvas draws the mark in Literata 600 at opsz 72 (brand-canvas.dc.html, 40-1 and
// 40-A-3), and the app bundles Literata only at 400/500. So the assets are cut from the canvas's own rendering — the same
// Google Fonts face the canvas loads — exactly like the stage icons were cut from plan-canvas (PLAN-UI-2).
//
// Run from mobile/ (needs the playwright that backend2 already installs, and the network for the font):
//   node scripts/brand/render_brand.mjs            (PLAYWRIGHT=<…>/backend2/node_modules/playwright from a worktree)
// then the letters and the icon sizes:
//   xcrun swift scripts/brand/letters_split.swift && xcrun swift scripts/brand/letters_check.swift
//   xcrun swift scripts/brand/icon_sizes.swift
//
// Output:
//   assets/brand/wordmark.png (+ 2.0x/, 3.0x/) — «Ritora» in ink on transparent, box = the text line box of 41-1
//   assets/brand/r_mark.png   (+ 2.0x/, 3.0x/) — «R» alone, the same box rules (the launch screen and frame 41-1a)
//   lib/ui/brand/wordmark_metrics.dart — box widths and letter boundaries the splash animates by
//   ios/Runner/Assets.xcassets/LaunchImage.imageset/LaunchImage{,@2x,@3x}.png — the «R» for LaunchScreen.storyboard
//   build/brand/icon-1024.png — the icon master (icon_sizes.swift flattens and scales it into AppIcon.appiconset)
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(import.meta.url);
// A worktree has no node_modules of its own — point PLAYWRIGHT at a checkout that ran `npm i` in backend2/.
const { chromium } = require(path.resolve(process.env.PLAYWRIGHT ?? '../backend2/node_modules/playwright'));

const INK = '#2E2620'; // AppColors.ink
const PAPER = '#F6F3EC'; // AppColors.paper
const BRASS = '#8C6A3A'; // AppColors.brassInk
const PLINTH = '#1B1A18'; // AppColors.windowInk — the icon ground of 40-A-3

const FONT_LINK =
  '<link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,600&display=block" rel="stylesheet">';

// 41-1: Literata 600, opsz 72, 44/48, tracking −0.01em, ink.
const WORD_STYLE =
  "font-family:Literata;font-weight:600;font-variation-settings:'opsz' 72;font-size:44px;line-height:48px;" +
  `letter-spacing:-.01em;color:${INK};display:inline-block;white-space:nowrap;padding:0;margin:0`;

const page = (body) => `<!doctype html><html><head><meta charset="utf-8">${FONT_LINK}
<style>html,body{margin:0;padding:0;background:transparent}</style></head><body>${body}</body></html>`;

const browser = await chromium.launch();

async function open(html, scale, viewport = { width: 600, height: 300 }) {
  const p = await browser.newPage({ viewport, deviceScaleFactor: scale });
  await p.setContent(html, { waitUntil: 'load' });
  await p.evaluate(() => document.fonts.ready);
  const ok = await p.evaluate(() => document.fonts.check("600 44px Literata"));
  if (!ok) throw new Error('Literata 600 did not load — the assets would be drawn in a fallback face');
  return p;
}

fs.mkdirSync('assets/brand/2.0x', { recursive: true });
fs.mkdirSync('assets/brand/3.0x', { recursive: true });
fs.mkdirSync('build/brand', { recursive: true });

const variant = (scale, name) => (scale === 1 ? `assets/brand/${name}` : `assets/brand/${scale}.0x/${name}`);

// Each image on a page of its own, pinned at whole pixels: an element screenshot snaps its clip to whole CSS pixels, and
// a box that starts at a fraction would shift the glyph inside one image against the other — the launch «R» and the
// splash's «R» must land on the same device pixels.
//
// The word is also split into six letter images — the same line box, each pixel of the word given to the letter that
// covers it — so the splash can fade «itora» in letter by letter without slicing a glyph: Literata's «R» reaches past
// its advance under the «i», and a band cut at the advance would clip its leg. letters_split.swift does the split,
// letters_check.swift proves the six add up to the word.
const LETTERS = 'Ritora';

// [only] — paint only that letter (spans, the others transparent). Such a render is NOT the letter as the word draws
// it — spans split the shaping, and Literata's «r» before «a» comes out another glyph alone — so it is used only as a
// map of which letter covers which pixel (letters_split.swift), never shipped.
async function shoot(inner, scale, file, { width, left = 0, only } = {}) {
  const body = only === undefined
    ? inner
    : [...inner].map((ch, i) => `<span style="color:${i === only ? INK : 'transparent'}">${ch}</span>`).join('');
  const p = await open(page(`<div id="el" style="position:absolute;left:${left}px;top:0;${WORD_STYLE}${width ? `;width:${width}px` : ''}">${body}</div>`), scale);
  const box = await p.evaluate(() => {
    const r = document.getElementById('el').getBoundingClientRect();
    return { width: r.width, height: r.height };
  });
  const clip = { x: 0, y: 0, width: Math.ceil(left + box.width), height: Math.ceil(box.height) };
  await p.screenshot({ path: file, clip, omitBackground: true });
  await p.close();
  return box;
}

let metrics;
for (const scale of [1, 2, 3]) {
  if (scale === 1) {
    const p = await open(page(`<div id="el" style="position:absolute;left:0;top:0;${WORD_STYLE}">${LETTERS}</div>`), 1);
    metrics = await p.evaluate(() => {
      const el = document.getElementById('el');
      const text = el.firstChild;
      const box = el.getBoundingClientRect();
      const letters = [];
      for (let i = 0; i < text.length; i++) {
        const r = document.createRange();
        r.setStart(text, i);
        r.setEnd(text, i + 1);
        const b = r.getBoundingClientRect();
        letters.push({ left: b.left - box.left, right: b.right - box.left });
      }
      return { wordWidth: box.width, wordHeight: box.height, letters };
    });
    const mark = await open(page(`<div id="el" style="position:absolute;left:0;top:0;${WORD_STYLE}">R</div>`), 1);
    metrics.rWidth = await mark.evaluate(() => document.getElementById('el').getBoundingClientRect().width);
    await mark.close();
  }
  // The whole word — a check image, and what the static screens (41-4, the profile's sheets) draw.
  await shoot(LETTERS, scale, variant(scale, 'wordmark.png'));
  fs.mkdirSync(`build/brand/${scale}x`, { recursive: true });
  for (let k = 0; k < LETTERS.length; k++) {
    await shoot(LETTERS, scale, `build/brand/${scale}x/shape_${k}.png`, { only: k });
  }
  // «R» alone, 8 px of air on both sides of its 32 px box: its leg overhangs the advance, and the launch screen centres
  // the image — so the box sits in the middle of it, at a whole pixel.
  await shoot('R', scale, variant(scale, 'r_mark.png'), { left: 8, width: 32 + 8 });
}

// The launch screen shows the same «R» — one file for both, so the hand-off from iOS to Flutter is pixel for pixel.
const launch = 'ios/Runner/Assets.xcassets/LaunchImage.imageset';
fs.copyFileSync('assets/brand/r_mark.png', `${launch}/LaunchImage.png`);
fs.copyFileSync('assets/brand/2.0x/r_mark.png', `${launch}/LaunchImage@2x.png`);
fs.copyFileSync('assets/brand/3.0x/r_mark.png', `${launch}/LaunchImage@3x.png`);

// 40-A-3: 1024 plinth, the «R.» symbol d-dot-icon (text R at x 0, baseline 100, size 100; brass circle cx 92, cy 90,
// r 10) in a viewBox 0 22 106 82 drawn 278 × 215 on the 512 preview — 556 × 430 on the 1024 master, centred.
const icon = `<div style="width:1024px;height:1024px;background:${PLINTH};display:flex;align-items:center;justify-content:center">
<svg viewBox="0 22 106 82" width="556" height="430" style="display:block">
<text x="0" y="100" fill="${PAPER}" font-family="Literata" font-weight="600" font-size="100" style="font-variation-settings:'opsz' 72">R</text>
<circle cx="92" cy="90" r="10" fill="${BRASS}"></circle></svg></div>`;
{
  const p = await open(page(icon), 1, { width: 1024, height: 1024 });
  await p.screenshot({ path: 'build/brand/icon-1024.png', clip: { x: 0, y: 0, width: 1024, height: 1024 } });
  await p.close();
}
await browser.close();

const r = (v) => Math.round(v * 100) / 100;
const cuts = metrics.letters.slice(1).map((l) => r(l.left));
const dart = `// GENERATED by scripts/brand/render_brand.mjs — do not edit by hand; re-run the script.
//
// The line boxes of the wordmark images (assets/brand/wordmark.png, r_mark.png) in logical pixels, measured in the same
// rendering the images were cut from (Literata 600, opsz 72, 44/48, tracking −0.01em — frame 41-1).

/// Width of the «Ritora» line box — the image's logical width.
const double kWordmarkWidth = ${r(metrics.wordWidth)};

/// Height of both line boxes — the 48 of «44/48».
const double kWordmarkHeight = ${r(metrics.wordHeight)};

/// Width of the «R» line box alone — frame 41-1a centres this box, and so does the launch screen.
const double kWordmarkRWidth = ${r(metrics.rWidth)};

/// The word's images are whole logical pixels wide (a screenshot clip snaps up), the glyphs at x 0 — the extra
/// fraction is empty on the right. r_mark.png is wider than its «R»: the leg overhangs the advance, so the box sits
/// [kRMarkInset] in, with the same air on the left, and the launch screen can centre the image.
const double kWordmarkImageWidth = ${Math.ceil(metrics.wordWidth)};
const double kRMarkImageWidth = 48;

/// The «R» box starts this far inside r_mark.png (8 px of air for its leg on the right, the same on the left).
const double kRMarkInset = 8;

/// Where letters 1…5 («i», «t», «o», «r», «a») start inside the word box — the splash reveals the word by these cuts.
const List<double> kWordmarkLetterStarts = [${cuts.join(', ')}];
`;
fs.mkdirSync('lib/ui/brand', { recursive: true });
fs.writeFileSync('lib/ui/brand/wordmark_metrics.dart', dart);
console.log(JSON.stringify(metrics, null, 1));

/*
 * An admin's look for an occasion (Administration → Fanfare → Customize):
 * other words, other art, another way in, other effects, another sound.
 * Whatever the look leaves empty stays Fanfare's own.
 */
import type { Look, Moment } from '../types';
import type { FX } from './fx';
import * as A from './art';
import { TUNES } from './music';
import { SCENES, type Scene } from './scenes';
import { long, safeUrl } from './markup';
import { $, $$, esc, st } from './state';

/** The look for a moment: Festoon days by their own key (custom-<id>), everything else by its id. */
export function lookFor(m: Moment): Look {
  const key = String(m.key ?? '');
  return st.host.looks?.[key.startsWith('custom-') ? key : m.id] || {};
}

/** The moment's scene with the look's art and sound. */
export function buildScene(m: Moment, look: Look): Scene | null {
  const make = SCENES[m.id];
  if (!make) return null;
  let s = make(m);
  const other = look.art?.startsWith('scene:') ? look.art.slice(6) : '';
  const image = look.image && safeUrl(look.image) !== '#' ? look.image : '';

  if (other && other !== 'custom' && SCENES[other] && SCENES[other] !== make) {
    // Another occasion's centrepiece, carrying this occasion's own words.
    const own = words(s);
    s = { ...SCENES[other](borrowed(m)), own: { title: own.title, sub: own.sub } };
  } else if (look.art === 'emoji' && look.emoji) {
    s = onPlaque(s, A.rosetteSVG(s.btn, s.ink, esc(look.emoji)));
  } else if (look.art === 'image' && image) {
    s = onPlaque(s, pictureSVG(image));
  }

  return withSound(s, look);
}

/** What another occasion's scene reads from its moment, for a moment that has none of it. */
function borrowed(m: Moment): Moment {
  const today = new Date().toISOString().slice(0, 10);
  return { years: 1, night: 8, year: new Date().getFullYear(), age: 1, since: today, members: 0, posts: 0, count: 0, ...m };
}

/** The scene's own title, first line under it and buttons, as plain text (and the buttons' markup). */
function words(s: Scene) {
  const tpl = document.createElement('template');
  tpl.innerHTML = s.titleHtml + s.footHtml;
  const text = (el: Node | null): string =>
    !el ? '' : el.nodeType === 3 ? (el.textContent || '').trim() : Array.from(el.childNodes).map(text).filter(Boolean).join(' ');
  return {
    title: text(tpl.content.querySelector('#ffTitle')),
    sub: text(tpl.content.querySelector('.Sub')),
    acts: acts(tpl.content.querySelector('.Acts')),
  };
}

/** The scene's buttons, without the ones that act on its own art (the cake's candles, the present's lid). */
function acts(el: Element | null) {
  if (!el) return '';
  el.querySelectorAll('#ffWish, #ffOpen').forEach((button) => button.remove());
  return el.outerHTML;
}

/** Emoji or picture art: the greeting and the buttons move onto the plaque at its foot. */
function onPlaque(s: Scene, svg: string): Scene {
  const own = words(s);
  return {
    ...s,
    svg: () => svg,
    title: [0, 0, 0, 0],
    foot: [74.5, 14, 72, 23],
    titleHtml: '',
    footHtml: `<h2 class="Script t-anniv${long(own.title)}${own.title.length > 40 ? ' longer' : ''}" id="ffTitle" tabindex="-1">${esc(own.title)}</h2>
      ${own.sub ? `<p class="Sub sub-anniv">${esc(own.sub)}</p>` : ''}${own.acts}`,
  };
}

/** The admin's picture over the same white plaque the other centrepieces have. */
function pictureSVG(url: string) {
  return `<svg viewBox="0 0 600 700"><defs>${A.DEFS}
      <linearGradient id="ff-iplaque" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#f1f4fa"/></linearGradient></defs>
    <image class="LookImage" href="${esc(url)}" x="30" y="0" width="540" height="490" preserveAspectRatio="xMidYMid meet"/>
    <rect x="70" y="500" width="460" height="192" rx="26" fill="url(#ff-iplaque)" stroke="url(#ff-gold)" stroke-width="4"/></svg>`;
}

/** No sound at all, another of the tunes, or the admin's own file. */
function withSound(s: Scene, look: Look): Scene {
  if (look.sound === 'none') {
    const strip = (html: string) =>
      html
        .replace(/<button class="Play" id="ffPlay"[\s\S]*?<\/button>/, '')
        .replace('<button class="PRound" id="ffMute" type="button"></button>', '');
    return { ...s, tune: null, titleHtml: strip(s.titleHtml), footHtml: strip(s.footHtml) };
  }
  if (look.sound === 'upload' && look.soundUrl && safeUrl(look.soundUrl) !== '#') return { ...s, tune: null, audio: look.soundUrl };
  if (look.sound && TUNES[look.sound]) return { ...s, tune: look.sound };
  return s;
}

/** After the piece is drawn: the look's title and line under it, {name} and {forum} filled in. */
export function applyText(s: Scene, look: Look) {
  const fill = (text: string) => text.replace(/\{name\}/g, st.host.name).replace(/\{forum\}/g, st.host.forum);
  const titleEl = $('#ffTitle');
  if (!titleEl) return;
  const title = look.title ? fill(look.title) : s.own?.title;
  if (title) {
    titleEl.textContent = title;
    if (titleEl.classList.contains('t-anniv')) {
      titleEl.classList.toggle('long', title.length > 24);
      titleEl.classList.toggle('longer', title.length > 40);
    }
  }

  // A borrowed centrepiece drops the lines that belong to its own occasion.
  if (s.own) $$('.Piece .cn, .Piece .yr, .Piece .yr2').forEach((el) => el.remove());
  const sub = look.subtitle ? fill(look.subtitle) : s.own?.sub;
  if (sub === undefined) return;
  let subEl = $('.Piece .Sub');
  if (!sub) {
    $$('.Piece .Sub').forEach((el) => el.remove());
    return;
  }
  if (!subEl) {
    subEl = document.createElement('p');
    subEl.className = 'Sub sub-look';
    titleEl.after(subEl);
  }
  subEl.textContent = sub;
}

/** The root's classes for the look: how the piece comes in. */
export const lookClass = (look: Look) => (look.animation && /^(pop|float|none)$/.test(look.animation) ? ' anim-' + look.animation : '');

/** The effects the scene may use: none of its own when the look names its effect (or none). */
export function sceneFx(all: FX, look: Look): FX {
  if (!look.effect) return all;
  const nothing = () => {};
  return {
    ...all,
    confetti: nothing,
    pop: nothing,
    sparkle: nothing,
    leaves: nothing,
    balloons: nothing,
    snow: nothing,
    fireworks: nothing,
    lanterns: nothing,
  };
}

const EFFECTS: Record<string, (fx: FX) => void> = {
  confetti: (fx) => fx.confetti(200),
  snow: (fx) => fx.snow(90),
  fireworks: (fx) => fx.fireworks(6),
  lanterns: (fx) => fx.lanterns('amber', 16),
  leaves: (fx) => fx.leaves(70),
};

/** The look's own effect, in place of the scene's. */
export function playEffect(all: FX, look: Look) {
  EFFECTS[look.effect || '']?.(all);
}

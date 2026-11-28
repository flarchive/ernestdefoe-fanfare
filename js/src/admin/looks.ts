import app from 'flarum/admin/app';
import m from 'mithril';
import type Look from './models/Look';

/** The admin's looks, loaded once for the whole settings page (a handful of rows). */
const state = { loading: false, loaded: false };

export function loadLooks() {
  if (state.loading || state.loaded) return;
  state.loading = true;
  app.store
    .find<Look[]>('fanfare-looks')
    .then(() => {
      state.loaded = true;
    })
    .finally(() => {
      state.loading = false;
      m.redraw();
    });
}

export const looksLoaded = () => state.loaded;

export const lookFor = (occasion: string) => app.store.all<Look>('fanfare-looks').find((look) => look.occasion() === occasion);

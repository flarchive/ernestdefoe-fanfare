import app from 'flarum/forum/app';
import m from 'mithril';
import type Mithril from 'mithril';
import FieldSet from 'flarum/common/components/FieldSet';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import Button from 'flarum/common/components/Button';
import type Checkbox from 'flarum/common/components/Checkbox';
import type ItemList from 'flarum/common/utils/ItemList';
import type User from 'flarum/common/models/User';
import type SettingsPage from 'flarum/forum/components/SettingsPage';
import { t } from './util';
import type Birthday from './models/Birthday';

/** The birthday fields as the member edits them, kept on the settings page between redraws. */
interface BirthdayState {
  month: number | null;
  day: number | null;
  year: string | number | null;
  visibility: number;
  saving: boolean;
  /** Loaded from `fanfare-birthdays` when the page opens; null until then. */
  model: Birthday | null;
}

/** Saving the birthday: any attributes, or the date once it is whole and changed. */
interface Saver {
  save: (attrs: Record<string, unknown>) => void;
  saveDate: () => void;
}

/**
 * Settings → Fanfare: the member's birthday (month and day, year optional),
 * who sees it, and their sound and effects switches. With the Birthdays
 * extension installed the birthday is set there instead.
 */
export default function settingsItems(this: SettingsPage & { fanfare?: BirthdayState }, items: ItemList<Mithril.Children>) {
  const user = this.user!;
  const prefs = user.preferences() || {};
  const own = !!app.forum.attribute('fanfareOwnBirthday');
  const bd = (this.fanfare ??= load(user, own));

  const birthday = !own ? <p className="helpText">{t('settings.datlechin')}</p> : bd.model ? birthdayFields(bd, saver(bd)) : null;

  items.add(
    'fanfare',
    <FieldSet className="Settings-fanfare FieldSet--min" label={t('settings.heading')}>
      {birthday}
      {pref(user, prefs, 'fanfareSound', t('settings.sound'))}
      {pref(user, prefs, 'fanfareEffects', t('settings.effects'))}
    </FieldSet>,
    -5
  );
}

function saver(bd: BirthdayState): Saver {
  const save = (attrs: Record<string, unknown>) => {
    bd.saving = true;
    bd.model!.save(attrs).finally(() => {
      bd.saving = false;
      m.redraw();
    });
  };

  const saveDate = () => {
    if (!bd.month || !bd.day) return;
    const md = `${pad(bd.month)}-${pad(bd.day)}`;
    if (md === bd.model!.md() && String(bd.year || '') === String(bd.model!.year() || '')) return;
    const year = Number(bd.year);
    if (bd.year && (!Number.isInteger(year) || year < 1900 || year > new Date().getFullYear())) return;
    save({ md, year: bd.year ? year : null });
  };

  return { save, saveDate };
}

function birthdayFields(bd: BirthdayState, s: Saver) {
  return (
    <div className="Form-group FanfareSettings-birthday">
      <label>{t('settings.birthday')}</label>
      <div className="FanfareSettings-row">
        {monthSelect(bd, s)}
        {daySelect(bd, s)}
        {yearInput(bd, s)}
        {bd.month || bd.day ? (
          <Button
            className="Button Button--link"
            disabled={bd.saving}
            onclick={() => {
              bd.month = bd.day = bd.year = null;
              s.save({ md: null, year: null });
            }}
          >
            {t('settings.clear')}
          </Button>
        ) : null}
      </div>
      <label className="FanfareSettings-label">{t('settings.visibility')}</label>
      <Select
        value={String(bd.visibility)}
        options={{ 0: t('settings.visibility_0'), 1: t('settings.visibility_1'), 2: t('settings.visibility_2') }}
        disabled={bd.saving}
        onchange={(v: string) => {
          bd.visibility = Number(v);
          s.save({ visibility: bd.visibility });
        }}
      />
    </div>
  );
}

function monthSelect(bd: BirthdayState, s: Saver) {
  const months: Record<string, string> = {};
  const fmt = new Intl.DateTimeFormat(app.translator.getLocale?.() || 'en', { month: 'long' });
  // String keys: integer-like keys would be sorted ahead of the placeholder.
  for (let i = 1; i <= 12; i++) months['m' + i] = fmt.format(new Date(2000, i - 1, 1));

  return (
    <Select
      value={bd.month ? 'm' + bd.month : ''}
      options={{ '': t('settings.month'), ...months }}
      disabled={bd.saving}
      onchange={(v: string) => {
        bd.month = Number(v.slice(1)) || null;
        if ((bd.day ?? 0) > new Date(2000, bd.month || 1, 0).getDate()) bd.day = null;
        s.saveDate();
      }}
    />
  );
}

function daySelect(bd: BirthdayState, s: Saver) {
  const days: Record<string, string> = {};
  for (let i = 1; i <= new Date(2000, bd.month || 1, 0).getDate(); i++) days['d' + i] = String(i);

  return (
    <Select
      value={bd.day ? 'd' + bd.day : ''}
      options={{ '': t('settings.day'), ...days }}
      disabled={bd.saving}
      onchange={(v: string) => {
        bd.day = Number(v.slice(1)) || null;
        s.saveDate();
      }}
    />
  );
}

function yearInput(bd: BirthdayState, s: Saver) {
  return (
    <input
      className="FormControl FanfareSettings-year"
      type="number"
      inputmode="numeric"
      min="1900"
      max={new Date().getFullYear()}
      placeholder={t('settings.year')}
      aria-label={t('settings.year')}
      value={bd.year || ''}
      oninput={(e: InputEvent) => {
        const year = (e.target as HTMLInputElement).value;
        bd.year = year;
        // Android's keyboard can close without the field losing focus, so no change
        // event comes: a full four-digit year is saved as it is typed.
        if (/^\d{4}$/.test(year)) s.saveDate();
      }}
      onchange={s.saveDate}
    />
  );
}

function pref(user: User, prefs: Record<string, unknown>, key: string, label: Mithril.Children) {
  return (
    <Switch
      state={prefs[key] !== false}
      onchange={(value: boolean, component: Checkbox & { loading?: boolean }) => {
        component.loading = true;
        user.savePreferences({ [key]: value }).then(() => {
          component.loading = false;
          m.redraw();
        });
      }}
    >
      {label}
    </Switch>
  );
}

/** The member's birthday, fetched once when Settings opens (not carried by every page). */
function load(user: User, own: boolean): BirthdayState {
  const bd: BirthdayState = { month: null, day: null, year: '', visibility: 1, saving: false, model: null };
  if (!own) return bd;

  app.store.find<Birthday>('fanfare-birthdays', user.id()!).then((model) => {
    const md = model.md();
    bd.month = md ? Number(md.slice(0, 2)) : null;
    bd.day = md ? Number(md.slice(3, 5)) : null;
    bd.year = model.year() || '';
    bd.visibility = model.visibility() ?? 1;
    bd.model = model;
    m.redraw();
  });

  return bd;
}

const pad = (n: number) => String(n).padStart(2, '0');

import app from 'flarum/admin/app';
import m from 'mithril';
import type Mithril from 'mithril';
import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Look from './models/Look';
import { lookFor } from './looks';

const NS = 'ernestdefoe-fanfare';
const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`${NS}.admin.looks.${key}`, params);
/** Plain text, for option labels and attributes. */
const tx = (key: string, params: Record<string, unknown> = {}) => extractText(t(key, params));

/** Fanfare's centrepieces a look can borrow (one per drawing: the two Eids and Thanksgivings share theirs). */
const SCENES = [
  'birthday',
  'anniversary',
  'forum',
  'newyear',
  'christmas',
  'thanksgiving',
  'halloween',
  'lunar',
  'diwali',
  'eidfitr',
  'hanukkah',
  'course',
  'trophy',
];
const SAME: Record<string, string> = { eidadha: 'eidfitr', thanksgiving_ca: 'thanksgiving' };
const TUNES = ['birthday', 'christmas', 'auld', 'chime', 'fanfare', 'spooky'];
const EFFECTS = ['confetti', 'snow', 'fireworks', 'lanterns', 'leaves'];

export interface LookModalAttrs extends IFormModalAttrs {
  occasion: string;
  label: string;
}

/**
 * Customize one occasion: its greeting, artwork, how it comes in, its effect
 * and its sound. Pictures and sounds upload as soon as they're chosen (the
 * look is made first if it doesn't exist yet); Reset deletes the look.
 */
export default class LookModal extends FormModal<LookModalAttrs> {
  look: Look | undefined;
  heading = Stream('');
  subtitle = Stream('');
  art = Stream('');
  emoji = Stream('');
  animation = Stream('rise');
  effect = Stream('');
  sound = Stream('');
  uploading: string | null = null;

  oninit(vnode: Mithril.Vnode<LookModalAttrs, this>) {
    super.oninit(vnode);
    this.look = lookFor(this.attrs.occasion);
    const look = this.look;
    if (!look) return;
    this.heading(look.title() || '');
    this.subtitle(look.subtitle() || '');
    this.art(look.art() || '');
    this.emoji(look.emoji() || '');
    this.animation(look.animation() || 'rise');
    this.effect(look.effect() || '');
    this.sound(look.sound() || '');
  }

  className() {
    return 'FanfareLookModal Modal--medium';
  }

  title() {
    return t('title', { name: this.attrs.label });
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.textFields()}
          {this.artFields()}
          <div className="Form-group">
            <label>{t('animation')}</label>
            <Select value={this.animation()} options={this.options('animation_', ['rise', 'pop', 'float', 'none'])} onchange={this.animation} />
          </div>
          <div className="Form-group">
            <label>{t('effect')}</label>
            <Select
              value={this.effect()}
              options={{ '': tx('own_effect'), none: tx('no_effect'), ...this.options('effect_', EFFECTS) }}
              onchange={this.effect}
            />
          </div>
          {this.soundFields()}
          {this.buttons()}
        </div>
      </div>
    );
  }

  textFields() {
    return [
      <div className="Form-group">
        <label for="FanfareLook-title">{t('title_label')}</label>
        <input id="FanfareLook-title" className="FormControl" maxlength={120} placeholder={tx('own_text')} bidi={this.heading} />
      </div>,
      <div className="Form-group">
        <label for="FanfareLook-subtitle">{t('subtitle')}</label>
        <input id="FanfareLook-subtitle" className="FormControl" maxlength={160} placeholder={tx('own_text')} bidi={this.subtitle} />
        {/* Passed as values so the translator leaves the placeholders as typed. */}
        <p className="helpText">{t('text_help', { name: '{name}', forum: '{forum}' })}</p>
      </div>,
    ];
  }

  artFields() {
    const own = SAME[this.attrs.occasion] || this.attrs.occasion;
    const scenes = Object.fromEntries(
      SCENES.filter((id) => id !== own).map((id) => ['scene:' + id, tx('scene_option', { name: tx('scenes.' + id) })])
    );
    const image = this.look?.imageUrl();
    return (
      <div className="Form-group">
        <label>{t('art')}</label>
        <Select value={this.art()} options={{ '': tx('own_art'), emoji: tx('art_emoji'), image: tx('art_image'), ...scenes }} onchange={this.art} />
        {this.art() === 'emoji' ? (
          <input className="FormControl FanfareLook-emoji" maxlength={16} aria-label={tx('emoji')} placeholder="🎉" bidi={this.emoji} />
        ) : null}
        {this.art() === 'image' ? (
          <div className="FanfareLook-file">
            {image ? <img className="FanfareLook-thumb" src={image} alt={tx('current_image')} /> : null}
            {this.fileInput('image', 'image/png,image/jpeg,image/webp', 'image_help')}
          </div>
        ) : null}
      </div>
    );
  }

  soundFields() {
    const url = this.look?.soundUrl();
    return (
      <div className="Form-group">
        <label>{t('sound')}</label>
        <Select
          value={this.sound()}
          options={{ '': tx('own_sound'), none: tx('no_sound'), ...this.options('tune_', TUNES), upload: tx('sound_upload') }}
          onchange={this.sound}
        />
        {this.sound() === 'upload' ? (
          <div className="FanfareLook-file">
            {url ? <audio className="FanfareLook-audio" controls preload="none" src={url} aria-label={tx('current_sound')} /> : null}
            {this.fileInput('sound', 'audio/mpeg,audio/ogg,.mp3,.ogg', 'sound_help')}
          </div>
        ) : null}
      </div>
    );
  }

  fileInput(kind: string, accept: string, help: string) {
    return (
      <div className="FanfareLook-upload">
        <input
          type="file"
          className="FormControl"
          accept={accept}
          aria-label={tx(kind === 'image' ? 'choose_image' : 'choose_sound')}
          disabled={!!this.uploading}
          onchange={(e: Event) => {
            const input = e.target as HTMLInputElement;
            const file = input.files?.[0];
            if (file) this.upload(kind, file).then(() => (input.value = ''));
          }}
        />
        <p className="helpText">{this.uploading === kind ? t('uploading') : t(help)}</p>
      </div>
    );
  }

  buttons() {
    return (
      <div className="Form-group Form-controls FanfareLook-buttons">
        <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={!!this.uploading}>
          {t('save')}
        </Button>
        {this.look ? (
          <Button className="Button" icon="fas fa-rotate-left" onclick={() => this.reset()} disabled={this.loading}>
            {t('reset')}
          </Button>
        ) : null}
        <a
          className="Button Button--flat"
          href={`${app.forum.attribute('baseUrl')}/?fanfare=${this.attrs.occasion === 'trophy' ? 'milestone' : this.attrs.occasion}`}
          target="_blank"
          rel="noopener"
        >
          <i className="fas fa-eye" aria-hidden="true" /> {t('preview')}
        </a>
      </div>
    );
  }

  /** Options keyed by the value itself (never integer-like, so their order holds). */
  options(prefix: string, values: string[]) {
    return Object.fromEntries(values.map((v) => [v, tx(prefix + v)]));
  }

  values() {
    return {
      title: this.heading().trim() || null,
      subtitle: this.subtitle().trim() || null,
      art: this.art(),
      emoji: this.emoji().trim() || null,
      animation: this.animation(),
      effect: this.effect(),
      sound: this.sound(),
    };
  }

  /** The look, saved with what the form says now; made first if this occasion has none yet. */
  save(): Promise<Look> {
    const look = this.look || app.store.createRecord<Look>('fanfare-looks');
    const attrs = this.look ? this.values() : { occasion: this.attrs.occasion, ...this.values() };
    // A new record comes back as the store's own copy, with its id.
    return look.save(attrs).then((saved) => (this.look = saved));
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();
    this.loading = true;
    this.save()
      .then(() => {
        this.hide();
        app.alerts.show({ type: 'success' }, t('saved'));
      })
      .catch(() => {
        // Core's request handler has shown what went wrong; the form stays open to fix it.
        this.loading = false;
        m.redraw();
      });
  }

  /** A picture or a sound, uploaded the moment it's chosen. The server sets the art or sound to it. */
  upload(kind: string, file: File): Promise<void> {
    this.uploading = kind;
    const body = new FormData();
    body.append('file', file);
    return this.save()
      .then((look) =>
        app
          .request<{ url: string }>({
            method: 'POST',
            url: `${app.forum.attribute('apiUrl')}/fanfare-looks/${look.id()}/${kind}`,
            serialize: (raw: FormData) => raw,
            body,
          })
          .then((res) => {
            look.pushAttributes(kind === 'image' ? { art: 'image', imageUrl: res.url } : { sound: 'upload', soundUrl: res.url });
            if (kind === 'image') this.art('image');
            else this.sound('upload');
          })
      )
      .catch(() => {
        // Core's request handler has shown the server's reason (type or size).
      })
      .finally(() => {
        this.uploading = null;
        m.redraw();
      });
  }

  reset() {
    if (!this.look) return;
    this.loading = true;
    this.look
      .delete()
      .then(() => {
        this.hide();
        app.alerts.show({ type: 'success' }, t('reset_done'));
      })
      .catch(() => {
        // Core's request handler has shown what went wrong.
        this.loading = false;
        m.redraw();
      });
  }
}

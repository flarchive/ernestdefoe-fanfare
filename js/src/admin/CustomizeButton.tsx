import app from 'flarum/admin/app';
import type Mithril from 'mithril';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LookModal from './LookModal';
import { loadLooks, lookFor, looksLoaded } from './looks';

const NS = 'ernestdefoe-fanfare';
const t = (key: string) => app.translator.trans(`${NS}.admin.looks.${key}`);

export interface CustomizeButtonAttrs extends ComponentAttrs {
  occasion: string;
  /** The occasion's name, as plain text, for the dialog's title. */
  label: string;
}

/** Opens the Customize dialog for one occasion; marked when the occasion has a look. */
export default class CustomizeButton extends Component<CustomizeButtonAttrs> {
  oninit(vnode: Mithril.Vnode<CustomizeButtonAttrs, this>) {
    super.oninit(vnode);
    loadLooks();
  }

  view() {
    const { occasion, label } = this.attrs;
    const customized = !!lookFor(occasion);
    return (
      <Button
        className={'Button Button--flat FanfareLook-customize' + (customized ? ' is-customized' : '')}
        icon="fas fa-palette"
        disabled={!looksLoaded()}
        onclick={() => app.modal.show(LookModal, { occasion, label })}
      >
        {t('customize')}
        {customized ? <span className="FanfareLook-mark">{t('customized')}</span> : null}
      </Button>
    );
  }
}

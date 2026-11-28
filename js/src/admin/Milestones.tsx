import app from 'flarum/admin/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import type Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import CustomizeButton from './CustomizeButton';

const NS = 'ernestdefoe-fanfare';
const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`${NS}.admin.milestones.${key}`, params);
/** Plain text, for option labels and attributes. */
const tx = (key: string, params: Record<string, unknown> = {}) => extractText(t(key, params));

interface Milestone {
  metric: string;
  tag?: number | null;
  at: number | string;
  title?: string;
  message?: string;
}

export interface MilestonesAttrs extends ComponentAttrs {
  setting: Stream<string>;
  data: { counts?: Record<string, number>; tags?: Record<string, string>; goals?: boolean };
}

/**
 * The forum's all-time milestones: what to count, at which number, and what to
 * say. One JSON setting, saved with the page's Save button. Each fires once,
 * when the count crosses it after it was set (fanfare:greet, hourly).
 */
export default class Milestones extends Component<MilestonesAttrs> {
  view() {
    const list = this.list();
    const { counts = {}, tags = {} } = this.attrs.data;
    const metrics: Record<string, string> = { members: tx('members'), discussions: tx('discussions'), posts: tx('posts') };
    if (Object.keys(tags).length) metrics.tag = tx('tag');
    // String keys: integer-like ones would be sorted ahead.
    const tagOptions = Object.fromEntries(Object.entries(tags).map(([id, name]) => ['t' + id, name]));

    return (
      <div className="Form-group FanfareMilestones">
        <h3 className="FanfareAdmin-heading">{t('heading')}</h3>
        <p className="helpText">
          {t('help', { members: counts.members ?? 0, discussions: counts.discussions ?? 0, posts: counts.posts ?? 0 })}{' '}
          {this.attrs.data.goals ? t('goals_help') : null}
        </p>
        {list.map((row, i) => this.row(row, i, metrics, tagOptions))}
        <div className="FanfareMilestones-actions">
          <Button className="Button" icon="fas fa-plus" onclick={() => this.add()}>
            {t('add')}
          </Button>
          <a className="Button Button--flat" href={`${app.forum.attribute('baseUrl')}/?fanfare=milestone`} target="_blank" rel="noopener">
            <i className="fas fa-eye" aria-hidden="true" /> {t('preview')}
          </a>
          <CustomizeButton occasion="trophy" label={tx('customize_label')} />
        </div>
      </div>
    );
  }

  /** One milestone: what to count (and in which tag), at which number, and what to say. */
  row(row: Milestone, i: number, metrics: Record<string, string>, tagOptions: Record<string, string>) {
    return (
      <div className="FanfareMilestones-row" key={i}>
        <Select value={row.metric} options={metrics} onchange={(v: string) => this.update(i, { metric: v })} />
        {row.metric === 'tag' ? (
          <Select
            value={row.tag ? 't' + row.tag : ''}
            options={{ '': tx('pick_tag'), ...tagOptions }}
            onchange={(v: string) => this.update(i, { tag: Number(v.slice(1)) || null })}
          />
        ) : null}
        <input
          className="FormControl FanfareMilestones-at"
          type="number"
          min="1"
          aria-label={tx('at')}
          placeholder={tx('at')}
          value={row.at}
          oninput={(e: InputEvent) => this.update(i, { at: (e.target as HTMLInputElement).value })}
        />
        <input
          className="FormControl"
          maxlength={120}
          aria-label={tx('title')}
          placeholder={this.example(row)}
          value={row.title || ''}
          oninput={(e: InputEvent) => this.update(i, { title: (e.target as HTMLInputElement).value })}
        />
        <input
          className="FormControl FanfareMilestones-message"
          maxlength={500}
          aria-label={tx('message')}
          placeholder={tx('message')}
          value={row.message || ''}
          oninput={(e: InputEvent) => this.update(i, { message: (e.target as HTMLInputElement).value })}
        />
        <Button className="Button Button--icon Button--flat" icon="fas fa-trash" aria-label={tx('remove')} onclick={() => this.remove(i)} />
      </div>
    );
  }

  /** The title members get when this one is left empty, for this row's number and metric. */
  example(row: Milestone): string {
    const count = new Intl.NumberFormat(app.translator.getLocale?.() || 'en').format(Number(row.at) || 0);
    const tag = row.tag ? this.attrs.data.tags?.[String(row.tag)] : null;
    const example =
      row.metric === 'tag'
        ? tag
          ? tx('example_tag', { count, tag })
          : tx('example_tag_unpicked', { count })
        : tx('example_' + (['members', 'discussions', 'posts'].includes(row.metric) ? row.metric : 'members'), { count });
    return tx('title_placeholder', { example });
  }

  list(): Milestone[] {
    try {
      const v = JSON.parse(this.attrs.setting() || '[]');
      return Array.isArray(v) ? v : [];
    } catch (e) {
      // A setting saved by hand that isn't JSON: start from none.
      return [];
    }
  }

  save(list: Milestone[]) {
    this.attrs.setting(JSON.stringify(list));
  }

  update(i: number, change: Partial<Milestone>) {
    const list = this.list();
    list[i] = { ...list[i], ...change };
    this.save(list);
  }

  /** A new row starts at the next round number ahead of the forum's members. */
  add() {
    const now = this.attrs.data.counts?.members ?? 0;
    let at = 100;
    // 100, 250, 500, 1,000, 2,500, 5,000, 10,000…
    for (let e = 2; at <= now && e < 10; e++) for (const m of [1, 2.5, 5]) if (at <= now) at = m * Math.pow(10, e);
    if (at <= now) at = now + 1;
    this.save([...this.list(), { metric: 'members', at }]);
  }

  remove(i: number) {
    this.save(this.list().filter((_, j) => j !== i));
  }
}

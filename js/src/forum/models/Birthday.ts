import Model from 'flarum/common/Model';

/** A member's birthday (`fanfare-birthdays`, id = the user's id), as Settings → Fanfare reads and saves it. */
export default class Birthday extends Model {
  md = Model.attribute<string | null>('md');
  year = Model.attribute<number | null>('year');
  visibility = Model.attribute<number>('visibility');
}

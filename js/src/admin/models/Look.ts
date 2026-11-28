import Model from 'flarum/common/Model';

/** An admin's look for one occasion (fanfare-looks). Empty fields keep Fanfare's own. */
export default class Look extends Model {
  occasion() {
    return Model.attribute<string>('occasion').call(this);
  }
  title() {
    return Model.attribute<string | null>('title').call(this);
  }
  subtitle() {
    return Model.attribute<string | null>('subtitle').call(this);
  }
  art() {
    return Model.attribute<string>('art').call(this);
  }
  emoji() {
    return Model.attribute<string | null>('emoji').call(this);
  }
  animation() {
    return Model.attribute<string>('animation').call(this);
  }
  effect() {
    return Model.attribute<string>('effect').call(this);
  }
  sound() {
    return Model.attribute<string>('sound').call(this);
  }
  imageUrl() {
    return Model.attribute<string | null>('imageUrl').call(this);
  }
  soundUrl() {
    return Model.attribute<string | null>('soundUrl').call(this);
  }
}

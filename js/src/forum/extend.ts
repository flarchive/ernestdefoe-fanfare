import Extend from 'flarum/common/extenders';
import { WishNotification, DayNotification } from './notifications';
import Birthday from './models/Birthday';

export default [
  new Extend.Notification().add('fanfareWish', WishNotification).add('fanfareDay', DayNotification),
  new Extend.Store().add('fanfare-birthdays', Birthday),
];

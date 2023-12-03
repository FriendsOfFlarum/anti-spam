import Extend from 'flarum/common/extenders';
import BlockedRegistration from './models/BlockedRegistration';

export default [
  new Extend.Store() //
    .add('blocked-registrations', BlockedRegistration),
];

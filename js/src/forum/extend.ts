import Extend from 'flarum/common/extenders';
import User from 'flarum/common/models/User';

import { default as extend } from '../common/extend';

export default [
  ...extend,

  new Extend.Model(User) //
    .attribute<boolean>('canSpamblock'),
];

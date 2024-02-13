import Extend from 'flarum/common/extenders';
import BlockedRegistration from './models/BlockedRegistration';
import ChallengeQuestion from './models/ChallengeQuestion';

export default [
  new Extend.Store() //
    .add('blocked-registrations', BlockedRegistration)
    .add('challenge-questions', ChallengeQuestion),
];

import Model from 'flarum/common/Model';

export default class ChallengeQuestion extends Model {
  question() {
    return Model.attribute<string>('question').call(this);
  }

  answer() {
    return Model.attribute<string>('answer').call(this);
  }

  caseSensitive() {
    return Model.attribute<boolean>('caseSensitive').call(this);
  }

  isActive() {
    return Model.attribute<boolean>('isActive').call(this);
  }

  createdAt() {
    return Model.attribute('createdAt', Model.transformDate).call(this);
  }

  updatedAt() {
    return Model.attribute('updatedAt', Model.transformDate).call(this);
  }
}

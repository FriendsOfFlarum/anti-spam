import Model from 'flarum/common/Model';

export default class BlockedRegistration extends Model {
  ip() {
    return Model.attribute<string>('ip').call(this);
  }

  email() {
    return Model.attribute<string>('email').call(this);
  }

  username() {
    return Model.attribute<string>('username').call(this);
  }

  sfsData() {
    return Model.attribute<string>('sfsData').call(this);
  }

  provider() {
    return Model.attribute<string | null>('provider').call(this);
  }

  providerData() {
    return Model.attribute<string | null>('providerData').call(this);
  }

  attemptedAt() {
    return Model.attribute('attemptedAt', Model.transformDate).call(this);
  }
}

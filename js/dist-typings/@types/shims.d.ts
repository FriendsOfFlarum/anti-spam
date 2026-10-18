import User from 'flarum/common/models/User';
import type Stream from 'flarum/common/utils/Stream';

declare module 'flarum/common/models/User' {
  export default interface User {
    canSpamblock(): boolean;
  }
}

declare module 'flarum/forum/components/SignUpModal' {
  export default interface SignUpModal {
    fofAntiSpamTrap: Stream<string>;
  }
}

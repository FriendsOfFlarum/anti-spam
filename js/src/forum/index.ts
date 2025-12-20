import app from 'flarum/forum/app';
import addSpamblockControls from './extenders/addSpamblockControls';
import addSpamFlagType from './extenders/addSpamFlagType';

export { default as extend } from './extend';

app.initializers.add('fof-anti-spam', () => {
  addSpamblockControls();
  addSpamFlagType();
});

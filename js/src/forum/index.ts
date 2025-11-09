import app from 'flarum/forum/app';
import addSpamblockControls from './extenders/addSpamblockControls';

export { default as extend } from './extend';

app.initializers.add('fof-anti-spam', () => {
  addSpamblockControls();
});

import app from 'flarum/admin/app';
import AntiSpamSettingsPage from './components/AntiSpamSettingsPage';

export { default as extend } from './extend';

app.initializers.add('fof-anti-spam', () => {
  //
});

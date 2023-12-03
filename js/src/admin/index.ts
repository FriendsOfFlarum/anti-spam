import app from 'flarum/admin/app';
import AntiSpamSettingsPage from './components/AntiSpamSettingsPage';

export { default as extend } from './extend';

app.initializers.add('fof/anti-spam', () => {
  app.extensionData
    .for('fof-anti-spam')
    .registerPage(AntiSpamSettingsPage)
    .registerPermission(
      {
        icon: 'fas fa-pastafarianism',
        label: app.translator.trans('fof-anti-spam.admin.permissions.spamblock_users_label'),
        permission: 'user.spamblock',
      },
      'moderate'
    );
});

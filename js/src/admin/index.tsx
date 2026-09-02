import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import DashboardPage from 'flarum/admin/components/DashboardPage';
import AntiSpamSettingsPage from './components/AntiSpamSettingsPage';
import BlockedRegistrationsWidget from './components/BlockedRegistrationsWidget';

export { default as extend } from './extend';

app.initializers.add('fof-anti-spam', () => {
  AntiSpamSettingsPage.register();

  extend(DashboardPage.prototype, 'availableWidgets', function (widgets) {
    // High enough to sit near the top: a forum being hit by spam wants that visible on
    // opening the dashboard, not scrolled past.
    widgets.add('fof-anti-spam-blocked', <BlockedRegistrationsWidget />, 30);
  });
});

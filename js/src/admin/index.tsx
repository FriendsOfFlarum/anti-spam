import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import DashboardPage from 'flarum/admin/components/DashboardPage';
import AntiSpamSettingsPage from './components/AntiSpamSettingsPage';
import BlockedRegistrationsWidget from './components/BlockedRegistrationsWidget';

export { default as extend } from './extend';

app.initializers.add('fof-anti-spam', () => {
  AntiSpamSettingsPage.register();

  // Only alongside flarum/statistics: this widget is built to sit next to its dashboard
  // widget, and on a forum without it a lone statistics panel is out of place.
  if (app.initializers.has('flarum-statistics')) {
    extend(DashboardPage.prototype, 'availableWidgets', function (widgets) {
      // Just after flarum/statistics' own widget, which registers at 20.
      widgets.add('fof-anti-spam-blocked', <BlockedRegistrationsWidget />, 15);
    });
  }
});

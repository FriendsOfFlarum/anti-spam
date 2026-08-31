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
      // Above flarum/statistics (20) and Horizon's queue widget (15). A forum being hit by
      // spam wants that visible on opening the dashboard, not scrolled past.
      widgets.add('fof-anti-spam-blocked', <BlockedRegistrationsWidget />, 30);
    });
  }
});

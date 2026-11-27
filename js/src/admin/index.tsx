import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import DashboardPage from 'flarum/admin/components/DashboardPage';
import FormGroup, { type IFormGroupAttrs } from 'flarum/common/components/FormGroup';
import BlockedRegistrationsWidget from './components/BlockedRegistrationsWidget';
import FlagAccountSetting from './components/FlagAccountSetting';

export { default as extend } from './extend';

app.initializers.add('fof-anti-spam', () => {
  extend(DashboardPage.prototype, 'availableWidgets', function (widgets) {
    // High enough to sit near the top: a forum being hit by spam wants that visible on
    // opening the dashboard, not scrolled past.
    widgets.add('fof-anti-spam-blocked', <BlockedRegistrationsWidget />, 30);
  });

  extend(FormGroup.prototype, 'customFieldComponents', function (items) {
    items.add('fof-anti-spam.flag-account', (attrs: IFormGroupAttrs) => <FlagAccountSetting setting={attrs.stream!} />);
  });
});

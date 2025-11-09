import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import { default as commonExtend } from '../common/extend';
import AntiSpamSettingsPage from './components/AntiSpamSettingsPage';

export default [
  ...commonExtend,

  new Extend.Admin() //
    .page(AntiSpamSettingsPage)
    .permission(
      () => ({
        icon: 'fas fa-pastafarianism',
        label: app.translator.trans('fof-anti-spam.admin.permissions.spamblock_users_label'),
        permission: 'user.spamblock',
      }),
      'moderate'
    ),
];

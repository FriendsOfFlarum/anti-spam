import { jest } from '@jest/globals';
import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';
import flatten from 'flat';
import jsYaml from 'js-yaml';
import fs from 'fs';
import { extend } from '../../../src/admin';
import AntiSpamSettingsPage from '../../../src/admin/components/AntiSpamSettingsPage';

const extension = (id: string) => ({
  id,
  version: '1.0.0',
  description: '',
  icon: { name: 'fas fa-cog' },
  links: {},
  extra: { 'flarum-extension': { title: id } },
});

const enabled = ['fof-anti-spam', 'flarum-flags', 'flarum-approval', 'flarum-tags'];

/** Where each setting was before 2.1, from the tab-per-route page's own search index. */
const tabOfSetting: Record<string, string> = {
  'fof-anti-spam.honeypot.enabled': 'sign-ups',
  'fof-anti-spam.honeypot.minSeconds': 'sign-ups',
  'fof-anti-spam.honeypot.strict': 'sign-ups',
  'fof-anti-spam.registrationThrottleSeconds': 'sign-ups',
  'fof-anti-spam.sfs-lookup': 'stopforumspam',
  'fof-anti-spam.regionalEndpoint': 'stopforumspam',
  'fof-anti-spam.username': 'stopforumspam',
  'fof-anti-spam.ip': 'stopforumspam',
  'fof-anti-spam.email': 'stopforumspam',
  'fof-anti-spam.emailhash': 'stopforumspam',
  'fof-anti-spam.frequency': 'stopforumspam',
  'fof-anti-spam.confidence': 'stopforumspam',
  'fof-anti-spam.maxListingAgeDays': 'stopforumspam',
  'fof-anti-spam.blockTorExitNodes': 'stopforumspam',
  'fof-anti-spam.blockedAsns': 'stopforumspam',
  'fof-anti-spam.api_key': 'stopforumspam',
  'fof-anti-spam.report_blocked_registrations': 'stopforumspam',
  'fof-anti-spam.content-filter.enabled': 'content-filter',
  'fof-anti-spam.content-filter.monitor_all_users': 'content-filter',
  'fof-anti-spam.content-filter.monitor_post_count': 'content-filter',
  'fof-anti-spam.content-filter.monitor_hours_old': 'content-filter',
  'fof-anti-spam.content-filter.held_email_domains': 'content-filter',
  'fof-anti-spam.content-filter.detect_phones': 'content-filter',
  'fof-anti-spam.content-filter.detect_local_phones': 'content-filter',
  'fof-anti-spam.content-filter.detect_emails': 'content-filter',
  'fof-anti-spam.content-filter.detect_urls': 'content-filter',
  'fof-anti-spam.content-filter.detect_repeats': 'content-filter',
  'fof-anti-spam.content-filter.burst_post_count': 'content-filter',
  'fof-anti-spam.content-filter.burst_minutes': 'content-filter',
  'fof-anti-spam.content-filter.allowed_domains': 'content-filter',
  'fof-anti-spam.content-filter.flag_threshold': 'content-filter',
  'fof-anti-spam.content-filter.spam_threshold': 'content-filter',
  'fof-anti-spam.content-filter.auto_unapprove': 'content-filter',
  'fof-anti-spam.content-filter.auto_flag': 'content-filter',
  'fof-anti-spam.moderation.system_user_id': 'content-filter',
  'fof-anti-spam.content-filter.blocked_words': 'content-filter',
  'fof-anti-spam.content-filter.advanced_block_patterns': 'content-filter',
  'fof-anti-spam.actions.deleteUser': 'spammer-actions',
  'fof-anti-spam.actions.deletePosts': 'spammer-actions',
  'fof-anti-spam.actions.deleteDiscussions': 'spammer-actions',
  'fof-anti-spam.actions.moveDiscussionsToTags': 'spammer-actions',
};

beforeAll(() => {
  bootstrapAdmin({
    extensions: Object.fromEntries(enabled.map((id) => [id, extension(id)])),
    settings: {
      extensions_enabled: JSON.stringify(enabled),
      'fof-anti-spam.honeypot.enabled': '0',
      'fof-anti-spam.content-filter.monitor_all_users': '0',
      'fof-anti-spam.content-filter.held_email_domains': '',
    },
    dbDriver: 'sqlite',
  });

  app.translator.addTranslations(flatten(jsYaml.load(fs.readFileSync(new URL('../../../../locale/en.yml', import.meta.url), 'utf8')) as object));

  ['flarum-flags', 'flarum-approval', 'flarum-tags'].forEach((id) => app.initializers.add(id, () => {}));

  extend.forEach((extender) => extender.extend(app, { name: 'fof-anti-spam', exports: {} }));

  app.boot();
});

function page(attrs: Record<string, unknown> = {}) {
  let instance!: AntiSpamSettingsPage;
  const out = mq(AntiSpamSettingsPage, { id: 'fof-anti-spam', ...attrs, oninit: (vnode: any) => (instance = vnode.state) });

  return Object.assign(out, { instance: () => instance });
}

test('every setting is registered, on the tab it was on before', () => {
  const settings = (app.registry.getSettings('fof-anti-spam') ?? []).filter((s) => typeof s !== 'function');
  const registered = Object.fromEntries(settings.map((s) => [(s as any).setting, app.registry.getSettingLocation('fof-anti-spam', s).tab?.id]));

  expect(registered).toEqual(tabOfSetting);
});

test('every registered setting is in a section of its own tab', () => {
  (app.registry.getSettings('fof-anti-spam') ?? []).forEach((setting) => {
    expect(app.registry.getSettingLocation('fof-anti-spam', setting).section).toBeDefined();
  });
});

test('the tabs keep their old ids and order, then the log, then permissions', () => {
  const instance = new AntiSpamSettingsPage();
  instance.extension = app.data.extensions['fof-anti-spam'];

  expect(
    instance
      .pageTabItems()
      .toArray()
      .map((tab) => tab.id)
  ).toEqual(['sign-ups', 'stopforumspam', 'content-filter', 'spammer-actions', 'blocked-registrations', 'permissions']);
});

test('settings show only while the setting they depend on makes them matter', () => {
  const signUps = page({ tab: 'sign-ups' });

  expect(signUps).toHaveElement('.FoFAntiSpam-tabIntro');
  expect(signUps).not.toContainRaw(app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_min_seconds_label', {}, true));

  signUps.instance().setting('fof-anti-spam.honeypot.enabled')('1');
  signUps.redraw();

  expect(signUps).toContainRaw(app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_min_seconds_label', {}, true));

  const contentFilter = page({ tab: 'content-filter' });
  const window = app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_label', {}, true);

  expect(contentFilter).toContainRaw(window);

  contentFilter.instance().setting('fof-anti-spam.content-filter.monitor_all_users')(true as any);
  contentFilter.redraw();
  expect(contentFilter).not.toContainRaw(window);

  contentFilter.instance().setting('fof-anti-spam.content-filter.held_email_domains')('mail.test');
  contentFilter.redraw();
  expect(contentFilter).toContainRaw(window);
});

test('the blocked registrations tab loads the log', () => {
  const find = jest.spyOn(app.store, 'find').mockResolvedValue(Object.assign([], { payload: { meta: { page: { total: 0 } } } }) as any);

  const blocked = page({ tab: 'blocked-registrations' });

  expect(blocked).toHaveElement('.FoFAntiSpamSettings--blockedRegistrations');
  expect(find).toHaveBeenCalledWith('blocked-registrations', expect.objectContaining({ page: { offset: 0, limit: 20 } }));

  find.mockRestore();
});

test('a link from before 2.1, with the tab in `page`, opens that tab', () => {
  const param = jest.spyOn(m.route, 'param').mockImplementation(((key?: string) => (key === 'page' ? 'content-filter' : undefined)) as any);
  const set = jest.spyOn(m.route, 'set').mockImplementation(() => {});

  page();

  expect(set).toHaveBeenCalledWith(expect.stringContaining('tab=content-filter'), undefined, { replace: true });

  param.mockRestore();
  set.mockRestore();
});

test('the flag account picker shows the unsaved pick after switching tabs', () => {
  const set = jest.spyOn(m.route, 'set').mockImplementation(() => {});
  const find = jest.spyOn(app.store, 'find').mockResolvedValue(app.store.getById('users', '1') as any);

  app.store.pushPayload({ data: { type: 'users', id: '2', attributes: { username: 'bob', displayName: 'Bob the moderator' } } });
  app.forum.pushAttributes({
    'fof-anti-spam.flagAccount': {
      id: '1',
      displayName: 'Admin',
      username: 'admin',
      avatarUrl: null,
      isDefault: true,
      configuredMissing: false,
      chosenInCode: false,
    },
  });

  const contentFilter = page({ tab: 'content-filter' });
  const instance = contentFilter.instance();

  instance.setting('fof-anti-spam.moderation.system_user_id')('2');
  instance.changePageTab('stopforumspam');
  contentFilter.redraw();
  instance.changePageTab('content-filter');
  contentFilter.redraw();

  expect(contentFilter).toContainRaw('Bob the moderator');

  set.mockRestore();
  find.mockRestore();
});

test('a failed StopForumSpam lookup is shown on its tab', () => {
  app.data.settings['fof-anti-spam.lookupFailedAt'] = new Date(Date.now() - 5 * 60 * 1000).toISOString();

  const sfs = page({ tab: 'stopforumspam' });

  expect(sfs).toHaveElement('.FoFAntiSpam-lookupFailed');

  delete app.data.settings['fof-anti-spam.lookupFailedAt'];
});

import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';
import flatten from 'flat';
import jsYaml from 'js-yaml';
import fs from 'fs';
import { extend } from '../../../src/admin';
import AntiSpamSettingsPage from '../../../src/admin/components/AntiSpamSettingsPage';

beforeAll(() => {
  const installed = ['fof-anti-spam', 'flarum-flags', 'flarum-approval', 'flarum-tags'];

  bootstrapAdmin({
    extensions: Object.fromEntries(
      installed.map((id) => [
        id,
        { id, version: '1.0.0', description: '', icon: { name: 'fas fa-cog' }, links: {}, extra: { 'flarum-extension': { title: id } } },
      ])
    ),
    // Safe mode can leave enabled extensions unloaded, so the list is not what decides.
    settings: { extensions_enabled: JSON.stringify(installed) },
    dbDriver: 'sqlite',
  });

  app.translator.addTranslations(flatten(jsYaml.load(fs.readFileSync(new URL('../../../../locale/en.yml', import.meta.url), 'utf8')) as object));

  extend.forEach((extender) => extender.extend(app, { name: 'fof-anti-spam', exports: {} }));

  app.boot();
});

test('settings for extensions that are not loaded are left out', () => {
  const keys = (app.registry.getSettings('fof-anti-spam') ?? []).map((s) => (s as any).setting);

  expect(keys).not.toContain('fof-anti-spam.content-filter.auto_unapprove');
  expect(keys).not.toContain('fof-anti-spam.content-filter.auto_flag');
  expect(keys).not.toContain('fof-anti-spam.moderation.system_user_id');
  expect(keys).not.toContain('fof-anti-spam.actions.moveDiscussionsToTags');
});

test('the content filter says how to get automatic actions', () => {
  const contentFilter = mq(AntiSpamSettingsPage, { id: 'fof-anti-spam', tab: 'content-filter' });

  expect(contentFilter).toContainRaw('Install flarum/approval and/or flarum/flags');
});

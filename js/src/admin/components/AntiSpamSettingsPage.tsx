import app from 'flarum/admin/app';
import ExtensionPage, { type ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import type { SettingsComponentOptions } from 'flarum/admin/components/AdminPage';
import type { TabItem } from 'flarum/common/components/Tabs';
import humanTime from 'flarum/common/helpers/humanTime';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import BlockedRegistrationList from './BlockedRegistrationList';

export default class AntiSpamSettingsPage extends ExtensionPage {
  static readonly BLOCKED_REGISTRATIONS_TAB = 'blocked-registrations';

  /** Read from the unsaved values. */
  static readonly shownWhen: Record<string, (page: AntiSpamSettingsPage) => boolean> = {
    'fof-anti-spam.honeypot.minSeconds': (page) => page.honeypotOn(),
    'fof-anti-spam.honeypot.strict': (page) => page.honeypotOn(),
    // The windows only narrow the filter when not everyone is monitored, but holding by email domain always uses them.
    'fof-anti-spam.content-filter.monitor_post_count': (page) => !page.monitoringEveryone() || page.holdingEmailDomains(),
    'fof-anti-spam.content-filter.monitor_hours_old': (page) => !page.monitoringEveryone() || page.holdingEmailDomains(),
  };

  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);

    // Before 2.1 each tab was a `page` route parameter, so links and bookmarks to them still carry one.
    // Changing the query rebuilds the page, so the tab opens on the rebuilt one rather than being loaded twice.
    const legacyTab = m.route.param('page');

    if (this.extension && !this.attrs.tab && legacyTab) {
      m.route.set(app.route('extension', { id: this.extension.id, tab: legacyTab }), undefined, { replace: true });
    }
  }

  pageTabItems(): ItemList<TabItem> {
    const items = super.pageTabItems();

    items.add(
      AntiSpamSettingsPage.BLOCKED_REGISTRATIONS_TAB,
      {
        id: AntiSpamSettingsPage.BLOCKED_REGISTRATIONS_TAB,
        label: app.translator.trans('fof-anti-spam.admin.blocked_registrations.button'),
        icon: 'fas fa-ban',
      },
      0
    );

    return items;
  }

  pageTabContent(tab: string, vnode: Mithril.VnodeDOM<ExtensionPageAttrs, this>): Mithril.Children {
    if (tab === AntiSpamSettingsPage.BLOCKED_REGISTRATIONS_TAB) return <BlockedRegistrationList />;

    const content = super.pageTabContent(tab, vnode);

    // The sign-up checks have no setting of their own to introduce them, so the tab carries the introduction.
    if (tab === 'sign-ups') {
      return [<p className="helpText FoFAntiSpam-tabIntro">{app.translator.trans('fof-anti-spam.admin.settings.sign_ups.introduction')}</p>, content];
    }

    return content;
  }

  buildSettingComponent(entry: ((this: this) => Mithril.Children) | SettingsComponentOptions): Mithril.Children {
    if (typeof entry !== 'function' && AntiSpamSettingsPage.shownWhen[entry.setting]?.(this) === false) return null;

    return super.buildSettingComponent(entry);
  }

  /**
   * Lookups fail open, so the admin needs to know when sign-ups have been going unchecked.
   */
  lookupFailureWarning(): Mithril.Children {
    const failedAt = app.data.settings['fof-anti-spam.lookupFailedAt'];

    if (!failedAt) return null;

    // Stored as an ISO 8601 string; humanTime wants a Date.
    const when = new Date(failedAt);

    if (isNaN(when.getTime())) return null;

    return (
      <div className="Alert Alert--error FoFAntiSpam-lookupFailed">
        {app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.lookup_failed', {
          when: humanTime(when),
        })}
      </div>
    );
  }

  /**
   * Read the way core's Switch reads it: the stored value is the string '0' once switched off.
   */
  honeypotOn(): boolean {
    const value = this.setting('fof-anti-spam.honeypot.enabled')();

    return !!value && value !== '0';
  }

  /**
   * Whether every user is being monitored, in which case the post-count and account-age
   * windows no longer narrow anything.
   */
  monitoringEveryone(): boolean {
    const value = this.setting('fof-anti-spam.content-filter.monitor_all_users')();

    return !!value && value !== '0';
  }

  /**
   * Whether any email domain is held in the admin panel. Domains held in code are not known here.
   */
  holdingEmailDomains(): boolean {
    return String(this.setting('fof-anti-spam.content-filter.held_email_domains')() ?? '').trim() !== '';
  }
}

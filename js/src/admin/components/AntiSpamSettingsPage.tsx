import Form from 'flarum/common/components/Form';
import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import type Mithril from 'mithril';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import BlockedRegistration from '../../common/models/BlockedRegistration';
import ItemList from 'flarum/common/utils/ItemList';
import extractText from 'flarum/common/utils/extractText';
import fullTime from 'flarum/common/helpers/fullTime';
import humanTime from 'flarum/common/helpers/humanTime';
import IPAddress from 'flarum/common/components/IPAddress';
import Pagination from 'flarum/common/components/Pagination';
import type { GeneralIndexItem } from 'flarum/admin/states/GeneralSearchIndex';
import BlockedRegistrationSearch from './BlockedRegistrationSearch';
import parseBlockedRegistrationQuery from '../utils/parseBlockedRegistrationQuery';
import BlockEvidenceSummary from './BlockEvidenceSummary';
import FlagAccountSetting from './FlagAccountSetting';

export type AntiSpamTab = {
  icon: string;
  label: Mithril.Children;
  content: () => Mithril.Children;
};

export default class AntiSpamSettingsPage extends ExtensionPage {
  private static readonly ITEMS_PER_PAGE: number = 20;

  /** Shown when the URL names no tab, or one that no longer exists. */
  static readonly DEFAULT_TAB: string = 'sign-ups';

  blockedLoading: boolean = false;
  blockedRegistrations: BlockedRegistration[] | null | undefined = null;

  currentPage: number = 1;
  /** Total matching records, from the API's page meta — not a page count. */
  total: number = 0;

  /** The raw `key:value` search query, parsed into filters when the request is made. */
  query: string = '';

  /** Total with no filters applied, to tell "nothing recorded" from "nothing matched". */
  totalUnfiltered: number = 0;

  /**
   * Every setting, for the admin search. `tree` names the tab, because a result opens the first
   * tab whatever its `link` says: core does not read an item's own link yet.
   */
  static searchIndex(): GeneralIndexItem[] {
    const tab = (page: string, label: Mithril.Children) => ({
      tree: [extractText(label)],
      link: app.route('extension', { id: 'fof-anti-spam', page }),
    });

    // Core's search calls `visible` whenever the key exists, so it is left off rather than undefined.
    const item = (
      where: ReturnType<typeof tab>,
      setting: string,
      label: Mithril.Children,
      help?: Mithril.Children,
      visible?: () => boolean
    ): GeneralIndexItem => ({
      id: setting,
      label: extractText(label),
      ...(help ? { help: extractText(help) } : {}),
      ...(visible ? { visible } : {}),
      ...where,
    });

    const signUps = tab('sign-ups', app.translator.trans('fof-anti-spam.admin.tabs.sign_ups'));
    const stopForumSpam = tab('stopforumspam', app.translator.trans('fof-anti-spam.admin.tabs.stopforumspam'));
    const contentFilter = tab('content-filter', app.translator.trans('fof-anti-spam.admin.tabs.content_filter'));
    const spammerActions = tab('spammer-actions', app.translator.trans('fof-anti-spam.admin.tabs.spammer_actions'));

    const approvalEnabled = () => app.initializers.has('flarum-approval');
    const flagsEnabled = () => app.initializers.has('flarum-flags');
    const tagsEnabled = () => app.initializers.has('flarum-tags');

    return [
      item(
        signUps,
        'fof-anti-spam.honeypot.enabled',
        app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_label'),
        app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_help')
      ),
      item(
        signUps,
        'fof-anti-spam.honeypot.minSeconds',
        app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_min_seconds_label'),
        app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_min_seconds_help')
      ),
      item(
        signUps,
        'fof-anti-spam.honeypot.strict',
        app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_strict_label'),
        app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_strict_help')
      ),
      item(
        signUps,
        'fof-anti-spam.registrationThrottleSeconds',
        app.translator.trans('fof-anti-spam.admin.settings.sign_ups.registration_throttle_label'),
        app.translator.trans('fof-anti-spam.admin.settings.sign_ups.registration_throttle_help')
      ),

      item(
        stopForumSpam,
        'fof-anti-spam.sfs-lookup',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_help')
      ),
      item(
        stopForumSpam,
        'fof-anti-spam.regionalEndpoint',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_help')
      ),
      item(stopForumSpam, 'fof-anti-spam.username', app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.username_label')),
      item(stopForumSpam, 'fof-anti-spam.ip', app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.ip_label')),
      item(stopForumSpam, 'fof-anti-spam.email', app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_label')),
      item(
        stopForumSpam,
        'fof-anti-spam.emailhash',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_hash_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_hash_help')
      ),
      item(
        stopForumSpam,
        'fof-anti-spam.frequency',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_help')
      ),
      item(
        stopForumSpam,
        'fof-anti-spam.confidence',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_help')
      ),
      item(
        stopForumSpam,
        'fof-anti-spam.maxListingAgeDays',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.max_listing_age_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.max_listing_age_help')
      ),
      item(
        stopForumSpam,
        'fof-anti-spam.blockTorExitNodes',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.block_tor_exit_nodes_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.block_tor_exit_nodes_help')
      ),
      item(
        stopForumSpam,
        'fof-anti-spam.blockedAsns',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.blocked_asns_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.blocked_asns_help')
      ),
      item(
        stopForumSpam,
        'fof-anti-spam.api_key',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_text')
      ),
      item(
        stopForumSpam,
        'fof-anti-spam.report_blocked_registrations',
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_label'),
        app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_help')
      ),

      item(
        contentFilter,
        'fof-anti-spam.content-filter.enabled',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.enabled_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.enabled_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.monitor_all_users',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_all_users_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_all_users_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.monitor_post_count',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.monitor_hours_old',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.detect_phones',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_phones_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_phones_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.detect_emails',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_emails_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_emails_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.detect_urls',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_urls_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_urls_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.allowed_domains',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.allowed_domains_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.allowed_domains_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.flag_threshold',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_threshold_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_threshold_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.spam_threshold',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.spam_threshold_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.spam_threshold_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.auto_unapprove',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_unapprove_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_unapprove_help'),
        approvalEnabled
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.auto_flag',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_flag_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_flag_help'),
        flagsEnabled
      ),
      item(
        contentFilter,
        'fof-anti-spam.moderation.system_user_id',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_help'),
        flagsEnabled
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.blocked_words',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.blocked_words_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.blocked_words_help')
      ),
      item(
        contentFilter,
        'fof-anti-spam.content-filter.advanced_block_patterns',
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.advanced_block_patterns_label'),
        app.translator.trans('fof-anti-spam.admin.settings.content-filter.advanced_block_patterns_help')
      ),

      item(
        spammerActions,
        'fof-anti-spam.actions.deleteUser',
        app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_user_label'),
        app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_user_help')
      ),
      item(
        spammerActions,
        'fof-anti-spam.actions.deletePosts',
        app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_posts_label'),
        app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_posts_help')
      ),
      item(
        spammerActions,
        'fof-anti-spam.actions.deleteDiscussions',
        app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_discussions_label'),
        app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_discussions_help')
      ),
      item(
        spammerActions,
        'fof-anti-spam.actions.moveDiscussionsToTags',
        app.translator.trans('fof-anti-spam.admin.settings.default-actions.move_discussions_to_tags_label'),
        app.translator.trans('fof-anti-spam.admin.settings.default-actions.move_discussions_to_tags_help'),
        tagsEnabled
      ),
    ];
  }

  /**
   * A lookup that cannot reach StopForumSpam lets the registration through. That is the right
   * call — better an open door than a forum nobody can join — but it must not happen quietly, or
   * an admin cannot tell a working forum from one that has been checking nothing for a week.
   */
  lookupFailureWarning() {
    const failedAt = app.data.settings['fof-anti-spam.lookupFailedAt'];

    if (!failedAt) return null;

    // Stored as an ISO 8601 string; humanTime wants a Date.
    const when = new Date(failedAt);

    if (isNaN(when.getTime())) return null;

    return (
      <div className="Alert Alert--error" style={{ marginBottom: '15px' }}>
        {app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.lookup_failed', {
          when: humanTime(when),
        })}
      </div>
    );
  }

  oninit(vnode: Mithril.Vnode<any, this>) {
    super.oninit(vnode);

    // Every tab is its own route and rebuilds the page, so this is where the list has to load,
    // whether the admin clicked through or landed here from a reload or a bookmark.
    if (this.currentTab(this.tabs()) === 'blocked-registrations') {
      this.loadData();
    }
  }

  /**
   * Each tab is a `page` route parameter, so it survives a reload and can be linked to.
   */
  tabs(): ItemList<AntiSpamTab> {
    const items = new ItemList<AntiSpamTab>();

    items.add(
      'sign-ups',
      {
        icon: 'fas fa-user-plus',
        label: app.translator.trans('fof-anti-spam.admin.tabs.sign_ups'),
        content: () => this.signUpsContent(),
      },
      50
    );

    items.add(
      'stopforumspam',
      {
        icon: 'fas fa-database',
        label: app.translator.trans('fof-anti-spam.admin.tabs.stopforumspam'),
        content: () => this.stopForumSpamContent(),
      },
      40
    );

    items.add(
      'content-filter',
      {
        icon: 'fas fa-filter',
        label: app.translator.trans('fof-anti-spam.admin.tabs.content_filter'),
        content: () => this.contentFilterContent(),
      },
      30
    );

    items.add(
      'spammer-actions',
      {
        icon: 'fas fa-user-slash',
        label: app.translator.trans('fof-anti-spam.admin.tabs.spammer_actions'),
        content: () => this.spammerActionsContent(),
      },
      20
    );

    items.add(
      'blocked-registrations',
      {
        icon: 'fas fa-ban',
        label: app.translator.trans('fof-anti-spam.admin.blocked_registrations.button'),
        content: () => this.blockedRegistrationsContent(),
      },
      10
    );

    return items;
  }

  currentTab(tabs: ItemList<AntiSpamTab>): string {
    const page = m.route.param('page');

    return page && tabs.has(page) ? page : AntiSpamSettingsPage.DEFAULT_TAB;
  }

  content() {
    const tabs = this.tabs();
    const current = this.currentTab(tabs);

    return (
      <div className="FoFAntiSpamSettings">
        <div className="container">
          {this.menuButtons(tabs, current)}
          {tabs.get(current).content()}
        </div>
      </div>
    );
  }

  menuButtons(tabs: ItemList<AntiSpamTab>, current: string): Mithril.Children {
    return (
      <div className="MenuButtons">
        {tabs.toArray().map((tab) => (
          <LinkButton
            className={`Button ${tab.itemName === current ? 'active' : ''}`}
            icon={tab.icon}
            href={app.route('extension', { id: 'fof-anti-spam', page: tab.itemName })}
            onclick={(e: MouseEvent) => this.confirmLeave(e)}
          >
            {tab.label}
          </LinkButton>
        ))}
      </div>
    );
  }

  /**
   * Moving to another tab rebuilds the page, which would otherwise drop unsaved edits without a word.
   */
  confirmLeave(e: MouseEvent) {
    // A modified click opens the tab somewhere else and leaves this page, edits and all, as it is.
    if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

    if (this.isChanged() && !confirm(extractText(app.translator.trans('fof-anti-spam.admin.unsaved_changes')))) {
      e.preventDefault();
    }
  }

  /**
   * Read the way core's Switch reads it: the stored value is the string '0' once switched off.
   */
  honeypotOn(): boolean {
    const value = this.setting('fof-anti-spam.honeypot.enabled')();

    return !!value && value !== '0';
  }

  /**
   * Every settings tab saves on its own. The page is rebuilt for each tab, so only the settings
   * shown here can be waiting to save.
   */
  settingsForm(section: Mithril.Children): Mithril.Children {
    return (
      <div className="FoFAntiSpamSettings--settings">
        <Form>
          {section}
          <hr />
          {this.submitButton()}
        </Form>
      </div>
    );
  }

  signUpsContent(): Mithril.Children {
    return this.settingsForm(
      <div className="Section Section--signUps">
        <h3>{app.translator.trans('fof-anti-spam.admin.settings.sign_ups.heading')}</h3>
        <p className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.sign_ups.introduction')}</p>

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_heading')}</h4>
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.honeypot.enabled',
          label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_help'),
        })}
        {this.honeypotOn() &&
          this.buildSettingComponent({
            type: 'number',
            setting: 'fof-anti-spam.honeypot.minSeconds',
            label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_min_seconds_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_min_seconds_help'),
            min: 0,
          })}
        {this.honeypotOn() &&
          this.buildSettingComponent({
            type: 'boolean',
            setting: 'fof-anti-spam.honeypot.strict',
            label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_strict_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_strict_help'),
          })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.sign_ups.throttle_heading')}</h4>
        {this.buildSettingComponent({
          type: 'number',
          setting: 'fof-anti-spam.registrationThrottleSeconds',
          label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.registration_throttle_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.registration_throttle_help'),
          min: 0,
        })}
      </div>
    );
  }

  stopForumSpamContent(): Mithril.Children {
    const apiRegions = ['closest', 'europe', 'us'];

    return this.settingsForm(
      <div className="Section Section--stopforumspam">
        <h3>StopForumSpam</h3>
        <div className="Introduction">
          <p className="helpText">
            {app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.introduction', {
              a: <Link href="https://stopforumspam.com" target="_blank" external={true} />,
            })}
          </p>
        </div>
        {this.lookupFailureWarning()}
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.sfs-lookup',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_help'),
        })}
        {this.buildSettingComponent({
          type: 'select',
          setting: 'fof-anti-spam.regionalEndpoint',

          options: apiRegions.reduce(
            (
              o: {
                [key: string]: string;
              },
              p
            ) => {
              o[p] = app.translator.trans(`fof-anti-spam.admin.settings.stopforumspam.region_${p}_label`) as string;
              return o;
            },
            {}
          ),

          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_help'),
        })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.checks_heading')}</h4>
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.username',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.username_label'),
        })}
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.ip',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.ip_label'),
        })}
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.email',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_label'),
        })}
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.emailhash',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_hash_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_hash_help'),
        })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.thresholds_heading')}</h4>
        {this.buildSettingComponent({
          type: 'number',
          setting: 'fof-anti-spam.frequency',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_help'),
          placeholder: '5',
          required: true,
        })}
        {this.buildSettingComponent({
          type: 'number',
          setting: 'fof-anti-spam.confidence',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_help'),
          min: 0,
          max: 100,
          placeholder: '50.0',
          required: true,
        })}
        {this.buildSettingComponent({
          type: 'number',
          setting: 'fof-anti-spam.maxListingAgeDays',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.max_listing_age_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.max_listing_age_help'),
          min: 0,
        })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.also_block_heading')}</h4>
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.blockTorExitNodes',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.block_tor_exit_nodes_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.block_tor_exit_nodes_help'),
        })}
        {this.buildSettingComponent({
          type: 'textarea',
          setting: 'fof-anti-spam.blockedAsns',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.blocked_asns_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.blocked_asns_help'),
        })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.reporting_heading')}</h4>
        <p className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_text')}</p>
        {this.buildSettingComponent({
          type: 'string',
          setting: 'fof-anti-spam.api_key',
          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_label'),

          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_instructions_text', {
            register: <a href="https://www.stopforumspam.com/forum/register.php" />,
            key: <a href="https://www.stopforumspam.com/keys" />,
          }),
        })}
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.report_blocked_registrations',

          label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_label'),

          help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_help'),
        })}
      </div>
    );
  }

  contentFilterContent(): Mithril.Children {
    const approvalEnabled = app.initializers.has('flarum-approval');
    const flagsEnabled = app.initializers.has('flarum-flags');

    return this.settingsForm(
      <div className="Section Section--contentFilter">
        <h3>{app.translator.trans('fof-anti-spam.admin.settings.content-filter.heading')}</h3>
        <p className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.content-filter.introduction')}</p>

        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.content-filter.enabled',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.enabled_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.enabled_help'),
        })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.content-filter.user_targeting_heading')}</h4>
        {this.buildSettingComponent({
          type: 'switch',
          setting: 'fof-anti-spam.content-filter.monitor_all_users',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_all_users_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_all_users_help'),
        })}

        {/*
          The two windows below only apply when everyone is not being monitored; leaving
          them enabled would suggest they still narrow anything.
        */}
        {!this.monitoringEveryone() && [
          this.buildSettingComponent({
            type: 'number',
            setting: 'fof-anti-spam.content-filter.monitor_post_count',
            label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_help'),
            placeholder: '5',
            min: 0,
          }),
          this.buildSettingComponent({
            type: 'number',
            setting: 'fof-anti-spam.content-filter.monitor_hours_old',
            label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_help'),
            placeholder: '24',
            min: 0,
          }),
        ]}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.content-filter.detectors_heading')}</h4>
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.content-filter.detect_phones',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_phones_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_phones_help'),
        })}

        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.content-filter.detect_emails',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_emails_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_emails_help'),
        })}

        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.content-filter.detect_urls',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_urls_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_urls_help'),
        })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.content-filter.thresholds_heading')}</h4>
        {this.buildSettingComponent({
          type: 'number',
          setting: 'fof-anti-spam.content-filter.flag_threshold',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_threshold_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_threshold_help'),
          placeholder: '50',
          min: 0,
          max: 100,
        })}

        {this.buildSettingComponent({
          type: 'number',
          setting: 'fof-anti-spam.content-filter.spam_threshold',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.spam_threshold_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.spam_threshold_help'),
          placeholder: '70',
          min: 0,
          max: 100,
        })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.content-filter.actions_heading')}</h4>
        {approvalEnabled &&
          this.buildSettingComponent({
            type: 'boolean',
            setting: 'fof-anti-spam.content-filter.auto_unapprove',
            label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_unapprove_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_unapprove_help'),
          })}

        {flagsEnabled &&
          this.buildSettingComponent({
            type: 'boolean',
            setting: 'fof-anti-spam.content-filter.auto_flag',
            label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_flag_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_flag_help'),
          })}

        {flagsEnabled && <FlagAccountSetting setting={this.setting('fof-anti-spam.moderation.system_user_id')} />}

        {!approvalEnabled && !flagsEnabled && (
          <p className="helpText Alert">{app.translator.trans('fof-anti-spam.admin.settings.content-filter.no_action_extensions')}</p>
        )}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.content-filter.allowlist_heading')}</h4>
        {this.buildSettingComponent({
          type: 'textarea',
          setting: 'fof-anti-spam.content-filter.allowed_domains',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.allowed_domains_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.allowed_domains_help'),
          placeholder: 'example.com\nyoutube.com\ngithub.com',
          rows: 5,
        })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.content-filter.blocked_words_heading')}</h4>
        {this.buildSettingComponent({
          type: 'textarea',
          setting: 'fof-anti-spam.content-filter.blocked_words',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.blocked_words_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.blocked_words_help'),
          placeholder: 'viagra\ncialis\ncrypto pump',
          rows: 8,
        })}

        <h4>{app.translator.trans('fof-anti-spam.admin.settings.content-filter.advanced_patterns_heading')}</h4>
        {this.buildSettingComponent({
          type: 'textarea',
          setting: 'fof-anti-spam.content-filter.advanced_block_patterns',
          label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.advanced_block_patterns_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.advanced_block_patterns_help'),
          placeholder: '[{"pattern": "/\\\\b(viagra|cialis)\\\\b/i", "description": "Pharmaceutical spam"}]',
          rows: 5,
        })}
      </div>
    );
  }

  spammerActionsContent(): Mithril.Children {
    const tagsEnabled = app.initializers.has('flarum-tags');

    return this.settingsForm(
      <div className="Section Section--defaultActions">
        <h3>{app.translator.trans('fof-anti-spam.admin.settings.default-actions.heading')}</h3>
        <p className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.default-actions.introduction')}</p>
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.actions.deleteUser',
          label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_user_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_user_help'),
        })}
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.actions.deletePosts',
          label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_posts_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_posts_help'),
        })}
        {this.buildSettingComponent({
          type: 'boolean',
          setting: 'fof-anti-spam.actions.deleteDiscussions',
          label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_discussions_label'),
          help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_discussions_help'),
        })}
        {tagsEnabled &&
          this.buildSettingComponent({
            type: 'flarum-tags.select-tags',
            setting: 'fof-anti-spam.actions.moveDiscussionsToTags',

            label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.move_discussions_to_tags_label'),

            help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.move_discussions_to_tags_help'),

            options: {
              requireParentTag: true,

              limits: {
                max: {
                  primary: 1,
                },

                min: {
                  primary: 1,
                },
              },
            },
          })}
      </div>
    );
  }

  blockedRegistrationsContent(): Mithril.Children {
    return (
      <div className="FoFAntiSpamSettings--blockedRegistrations">
        <Form>
          <h3>{app.translator.trans('fof-anti-spam.admin.blocked_registrations.title')}</h3>
          <p className="helpText">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.help')}</p>

          {/*
            The controls stay put whatever the results are. Rendering them only when there are
            rows would hide them the moment a filter matched nothing, leaving no way to undo it.
          */}
          {(this.hasRecords() || this.hasActiveFilters()) && (
            <BlockedRegistrationSearch
              query={this.query}
              loading={this.blockedLoading}
              onsubmit={(query: string) => {
                this.query = query;
                this.loadData(1);
              }}
            />
          )}

          {this.blockedLoading && <LoadingIndicator />}

          {!this.blockedLoading && this.blockedRegistrations && this.blockedRegistrations.length === 0 && (
            <div>
              <p>
                {this.hasActiveFilters()
                  ? app.translator.trans('fof-anti-spam.admin.blocked_registrations.filters.no-matches')
                  : app.translator.trans('fof-anti-spam.admin.blocked_registrations.no-records')}
              </p>
            </div>
          )}
          {!this.blockedLoading && this.blockedRegistrations && this.blockedRegistrations.length > 0 && (
            <div>
              <div className="BlockedRegistrations--list">
                {this.blockedRegistrations.map((blockedRegistration) => {
                  return (
                    <div className="BlockedRegistrations--item">
                      <div className="BlockedRegistrations-item--details">{this.detailItems(blockedRegistration).toArray()}</div>
                      <div className="BlockedRegistrations-item--actions">{this.actionItems(blockedRegistration).toArray()}</div>
                    </div>
                  );
                })}
              </div>
              <p className="BlockedRegistrations-total">
                {app.translator.trans('fof-anti-spam.admin.blocked_registrations.total', { count: this.total })}
              </p>
              {this.renderPagination()}
            </div>
          )}
        </Form>
      </div>
    );
  }

  async loadData(page: number = 1) {
    this.blockedLoading = true;
    m.redraw();

    try {
      const response = await app.store.find<BlockedRegistration[]>('blocked-registrations', {
        filter: parseBlockedRegistrationQuery(this.query),
        page: {
          offset: (page - 1) * AntiSpamSettingsPage.ITEMS_PER_PAGE,
          limit: AntiSpamSettingsPage.ITEMS_PER_PAGE,
        },
      });

      this.blockedRegistrations = response;

      // The endpoint counts the full result set and reports it in meta, so we can show a real
      // total instead of inferring "there is probably one more page" from a full page of rows.
      const total = response.payload?.meta?.page?.total;

      this.total = typeof total === 'number' ? total : response.length;

      if (!this.hasActiveFilters()) {
        this.totalUnfiltered = this.total;
      }
    } catch (error) {
      console.error(error);
      this.blockedRegistrations = [];
      this.total = 0;
    }

    this.blockedLoading = false;
    this.currentPage = page;
    m.redraw();
  }

  /**
   * Whether the table holds anything at all, as opposed to the current query matching nothing.
   * Recorded from the first unfiltered load so a query that matches nothing cannot make the
   * page claim the forum has never blocked a registration.
   */
  hasRecords(): boolean {
    return this.totalUnfiltered > 0;
  }

  /**
   * Whether every user is being monitored, in which case the post-count and account-age
   * windows no longer narrow anything.
   */
  monitoringEveryone(): boolean {
    return Boolean(this.setting('fof-anti-spam.content-filter.monitor_all_users')());
  }

  hasActiveFilters(): boolean {
    return this.query !== '';
  }

  renderPagination(): Mithril.Children {
    // Nothing to navigate when everything already fits on one page.
    if (this.total <= AntiSpamSettingsPage.ITEMS_PER_PAGE) return null;

    // Core's component rather than a bespoke one: it brings first/last buttons, a jump-to-page
    // input, translated labels and the a11y wiring, and stays consistent with the admin Users
    // page an admin has already learned.
    return (
      <Pagination
        currentPage={this.currentPage}
        loadingPageNumber={this.blockedLoading ? this.currentPage : undefined}
        total={this.total}
        perPage={AntiSpamSettingsPage.ITEMS_PER_PAGE}
        onChange={(page: number) => this.loadData(page)}
      />
    );
  }

  detailItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    const attemptedAt = blockedRegistration.attemptedAt();

    items.add(
      'attemptedAt',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.attempted-at')}</span>
        {/*
          No `?? new Date()` fallback here: a missing date rendered as "now" is indistinguishable
          from a registration that really was blocked seconds ago, which is how this went unnoticed.
        */}
        <span className="BlockedRegistrations-value">{attemptedAt ? fullTime(attemptedAt) : <em>&mdash;</em>}</span>
      </div>,
      100
    );

    items.add(
      'ip',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.ip')}</span>
        <span className="BlockedRegistrations-value">
          <IPAddress ip={blockedRegistration.ip()} />
        </span>
      </div>,
      90
    );

    items.add(
      'email',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.email')}</span>
        <span className="BlockedRegistrations-value">{blockedRegistration.email()}</span>
      </div>,
      80
    );

    items.add(
      'username',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.username')}</span>
        <span className="BlockedRegistrations-value">{blockedRegistration.username()}</span>
      </div>,
      70
    );

    if (blockedRegistration.provider()) {
      items.add(
        'provider',
        <div className="BlockedRegistrations-item--details">
          <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.login-provider')}</span>
          <span className="BlockedRegistrations-value">
            <code>{blockedRegistration.provider()}</code>
          </span>
        </div>,
        60
      );
    }

    items.add(
      'evidence',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.evidence.label')}</span>
        <span className="BlockedRegistrations-value">
          <BlockEvidenceSummary registration={blockedRegistration} />
        </span>
      </div>,
      20
    );

    // The full payloads stay available, but behind a click: they are reference material, not
    // something to read every time. Loaded on demand so the bundle does not carry the modal
    // for admins who never open it.
    items.add(
      'rawData',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label" />
        <span className="BlockedRegistrations-value">
          {/*
            Passed as a loader rather than a resolved component: app.modal.show accepts an async
            import, so the modal is fetched only when an admin actually opens it. The chunk is
            served via the jsDirectory registered in extend.php.
          */}
          <Button
            className="Button Button--text"
            icon="fas fa-code"
            onclick={() => app.modal.show(() => import('./RawDataModal'), { registration: blockedRegistration })}
          >
            {app.translator.trans('fof-anti-spam.admin.blocked_registrations.raw_data.button')}
          </Button>
        </span>
      </div>,
      10
    );

    return items;
  }

  actionItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'delete',
      <Button
        className="Button Button--danger"
        icon="fas fa-trash"
        onclick={() => {
          blockedRegistration.delete();
          this.blockedRegistrations = this.blockedRegistrations?.filter((b) => b.id() !== blockedRegistration.id());
          m.redraw();
        }}
      >
        {app.translator.trans('fof-anti-spam.admin.blocked_registrations.delete_entry')}
      </Button>
    );

    return items;
  }
}

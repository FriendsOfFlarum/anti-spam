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
import fullTime from 'flarum/common/helpers/fullTime';
import humanTime from 'flarum/common/helpers/humanTime';
import IPAddress from 'flarum/common/components/IPAddress';
import Pagination from 'flarum/common/components/Pagination';
import Input from 'flarum/common/components/Input';
import Select from 'flarum/common/components/Select';
import { debounce } from 'flarum/common/utils/throttleDebounce';
import BlockEvidenceSummary from './BlockEvidenceSummary';

export default class AntiSpamSettingsPage extends ExtensionPage {
  private static readonly ITEMS_PER_PAGE: number = 20;

  blockedLoading: boolean = false;
  blockedRegistrations: BlockedRegistration[] | null | undefined = null;

  currentPage: number = 1;
  /** Total matching records, from the API's page meta — not a page count. */
  total: number = 0;

  /** Free-text search across IP, email and username. */
  query: string = '';
  /** Restrict to one recorded block reason, or '' for all. */
  reason: string = '';
  /** Restrict to one login provider, or '' for all. */
  provider: string = '';
  /** A sort the endpoint accepts; '-attemptedAt' is its default. */
  sort: string = '-attemptedAt';

  /** Total with no filters applied, to tell "nothing recorded" from "nothing matched". */
  totalUnfiltered: number = 0;

  // Typing in the search box should not fire a request per keystroke.
  private search = debounce(250, () => this.loadData(1));

  static register() {
    app.registry.for('fof-anti-spam');

    // For search: Register settings with minimal info (label + help)
    // These are used by GeneralSearchSource for search indexing
    app.registry
      .registerSetting({
        type: 'switch',
        setting: 'fof-anti-spam.content-filter.enabled',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.enabled_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.enabled_help'),
      })
      .registerSetting({
        type: 'number',
        setting: 'fof-anti-spam.content-filter.monitor_post_count',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_help'),
      })
      .registerSetting({
        type: 'number',
        setting: 'fof-anti-spam.content-filter.monitor_hours_old',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_help'),
      })
      .registerSetting({
        type: 'switch',
        setting: 'fof-anti-spam.content-filter.detect_phones',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_phones_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_phones_help'),
      })
      .registerSetting({
        type: 'switch',
        setting: 'fof-anti-spam.content-filter.detect_emails',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_emails_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_emails_help'),
      })
      .registerSetting({
        type: 'switch',
        setting: 'fof-anti-spam.content-filter.detect_urls',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_urls_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_urls_help'),
      })
      .registerSetting({
        type: 'number',
        setting: 'fof-anti-spam.content-filter.flag_threshold',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_threshold_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_threshold_help'),
      })
      .registerSetting({
        type: 'number',
        setting: 'fof-anti-spam.content-filter.spam_threshold',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.spam_threshold_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.spam_threshold_help'),
      })
      .registerSetting({
        type: 'switch',
        setting: 'fof-anti-spam.content-filter.auto_unapprove',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_unapprove_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_unapprove_help'),
      })
      .registerSetting({
        type: 'switch',
        setting: 'fof-anti-spam.content-filter.auto_flag',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_flag_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_flag_help'),
      })
      .registerSetting({
        type: 'number',
        setting: 'fof-anti-spam.moderation.system_user_id',
        label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.system_user_id_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.system_user_id_help'),
      })
      .registerSetting({
        type: 'switch',
        setting: 'fof-anti-spam.sfs-lookup',
        label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_help'),
      })
      .registerSetting({
        type: 'number',
        setting: 'fof-anti-spam.frequency',
        label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_help'),
      })
      .registerSetting({
        type: 'number',
        setting: 'fof-anti-spam.confidence',
        label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_label'),
        help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_help'),
      });
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

    // Landing straight on the tab — a reload, or a bookmarked link — has to fetch as well,
    // since no menu click went through to start it.
    if (m.route.param('page') === 'blocked-registrations') {
      this.loadData();
    }
  }

  content() {
    // The route is the source of truth, so the tab survives a reload and can be linked to.
    // Holding it in component state meant every refresh dropped the admin back on Settings.
    const page = m.route.param('page') || 'settings';

    return (
      <div className="FoFAntiSpamSettings">
        <div className="container">
          {this.menuButtons(page)}
          {page === 'settings' && this.settingsContent()}
          {page === 'blocked-registrations' && this.blockedRegistrationsContent()}
        </div>
      </div>
    );
  }

  menuButtons(page: string): Mithril.Children {
    return (
      <div className="MenuButtons">
        <LinkButton
          className={`Button ${page === 'settings' ? 'active' : ''}`}
          icon="fas fa-cog"
          href={app.route('extension', { id: 'fof-anti-spam' })}
        >
          {app.translator.trans('fof-anti-spam.admin.settings.button')}
        </LinkButton>
        <LinkButton
          className={`Button ${page === 'blocked-registrations' ? 'active' : ''}`}
          icon="fas fa-ban"
          href={app.route('extension', { id: 'fof-anti-spam', page: 'blocked-registrations' })}
        >
          {app.translator.trans('fof-anti-spam.admin.blocked_registrations.button')}
        </LinkButton>
      </div>
    );
  }

  settingsContent(): Mithril.Children {
    const apiRegions = ['closest', 'europe', 'us'];
    const tagsEnabled = app.initializers.has('flarum-tags');
    const approvalEnabled = app.initializers.has('flarum-approval');
    const flagsEnabled = app.initializers.has('flarum-flags');

    return (
      <div className="FoFAntiSpamSettings--settings">
        <Form>
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
              type: 'number',
              setting: 'fof-anti-spam.content-filter.monitor_post_count',
              label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_help'),
              placeholder: '5',
              min: 0,
            })}

            {this.buildSettingComponent({
              type: 'number',
              setting: 'fof-anti-spam.content-filter.monitor_hours_old',
              label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_help'),
              placeholder: '24',
              min: 0,
            })}

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

            {flagsEnabled &&
              this.buildSettingComponent({
                type: 'number',
                setting: 'fof-anti-spam.moderation.system_user_id',
                label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.system_user_id_label'),
                help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.system_user_id_help'),
                placeholder: '1',
                min: 1,
              })}

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
              rows: 15,
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
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.sfs-lookup',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_help'),
            })}
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
              type: 'boolean',
              setting: 'fof-anti-spam.report_blocked_registrations',

              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_label'),

              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_help'),
            })}
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
            {this.buildSettingComponent({
              type: 'number',
              setting: 'fof-anti-spam.maxListingAgeDays',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.max_listing_age_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.max_listing_age_help'),
              min: 0,
            })}
            {this.buildSettingComponent({
              type: 'textarea',
              setting: 'fof-anti-spam.blockedAsns',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.blocked_asns_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.blocked_asns_help'),
            })}
            {this.buildSettingComponent({
              type: 'number',
              setting: 'fof-anti-spam.registrationThrottleSeconds',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.registration_throttle_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.registration_throttle_help'),
              min: 0,
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.blockTorExitNodes',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.block_tor_exit_nodes_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.block_tor_exit_nodes_help'),
            })}
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
          </div>
          <hr />
          {this.submitButton()}
        </Form>
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
          {(this.hasRecords() || this.hasActiveFilters()) && this.filterControls()}

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
      const filter: Record<string, string> = {};

      // Only send filters that are set: an empty value would narrow to rows whose column is
      // literally empty rather than matching everything.
      if (this.query) filter.q = this.query;
      if (this.reason) filter.reason = this.reason;
      if (this.provider) filter.provider = this.provider;

      const response = await app.store.find<BlockedRegistration[]>('blocked-registrations', {
        filter,
        sort: this.sort,
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

  filterControls(): Mithril.Children {
    const reasons = ['blacklisted', 'torExit', 'deniedAsn', 'confidence', 'frequency'];

    return (
      <div className="BlockedRegistrations-filters">
        <Input
          type="search"
          className="FormControl BlockedRegistrations-search"
          placeholder={app.translator.trans('fof-anti-spam.admin.blocked_registrations.filters.search_placeholder')}
          clearable={true}
          loading={this.blockedLoading}
          value={this.query}
          onchange={(value: string) => {
            this.query = value;
            this.search();
          }}
        />

        <Select
          value={this.reason}
          options={{
            '': app.translator.trans('fof-anti-spam.admin.blocked_registrations.filters.any_reason'),
            ...Object.fromEntries(
              reasons.map((reason) => [reason, app.translator.trans(`fof-anti-spam.admin.blocked_registrations.evidence.rule.${reason}`)])
            ),
          }}
          onchange={(value: string) => {
            this.reason = value;
            this.loadData(1);
          }}
        />

        <Select
          value={this.sort}
          options={{
            '-attemptedAt': app.translator.trans('fof-anti-spam.admin.blocked_registrations.filters.sort_newest'),
            attemptedAt: app.translator.trans('fof-anti-spam.admin.blocked_registrations.filters.sort_oldest'),
            username: app.translator.trans('fof-anti-spam.admin.blocked_registrations.filters.sort_username'),
            email: app.translator.trans('fof-anti-spam.admin.blocked_registrations.filters.sort_email'),
            ip: app.translator.trans('fof-anti-spam.admin.blocked_registrations.filters.sort_ip'),
          }}
          onchange={(value: string) => {
            this.sort = value;
            this.loadData(1);
          }}
        />

        {this.hasActiveFilters() && (
          <Button className="Button Button--text" icon="fas fa-times" onclick={() => this.clearFilters()}>
            {app.translator.trans('fof-anti-spam.admin.blocked_registrations.filters.clear')}
          </Button>
        )}
      </div>
    );
  }

  /**
   * Whether the table holds anything at all, as opposed to the current filters matching
   * nothing. Recorded from the first unfiltered load so a filter that matches nothing cannot
   * make the page claim there are no blocked registrations.
   */
  hasRecords(): boolean {
    return this.totalUnfiltered > 0;
  }

  hasActiveFilters(): boolean {
    return Boolean(this.query || this.reason || this.provider) || this.sort !== '-attemptedAt';
  }

  clearFilters(): void {
    this.query = '';
    this.reason = '';
    this.provider = '';
    this.sort = '-attemptedAt';

    this.loadData(1);
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

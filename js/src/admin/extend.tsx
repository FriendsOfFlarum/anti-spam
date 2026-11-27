import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import Link from 'flarum/common/components/Link';
import { default as commonExtend } from '../common/extend';
import AntiSpamSettingsPage from './components/AntiSpamSettingsPage';

// Initializers rather than the enabled list, which still names extensions that safe mode left out.
const flagsEnabled = () => app.initializers.has('flarum-flags');
const approvalEnabled = () => app.initializers.has('flarum-approval');
const tagsEnabled = () => app.initializers.has('flarum-tags');

export default [
  ...commonExtend,

  new Extend.Admin()
    .page(AntiSpamSettingsPage)

    .tab(() => ({ id: 'sign-ups', label: app.translator.trans('fof-anti-spam.admin.tabs.sign_ups'), icon: 'fas fa-user-plus' }))
    .tab(() => ({ id: 'stopforumspam', label: app.translator.trans('fof-anti-spam.admin.tabs.stopforumspam'), icon: 'fas fa-database' }))
    .tab(() => ({ id: 'content-filter', label: app.translator.trans('fof-anti-spam.admin.tabs.content_filter'), icon: 'fas fa-filter' }))
    .tab(() => ({ id: 'spammer-actions', label: app.translator.trans('fof-anti-spam.admin.tabs.spammer_actions'), icon: 'fas fa-user-slash' }))

    // Sign-ups
    .section(() => ({ id: 'honeypot', tab: 'sign-ups', label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_heading') }))
    .section(() => ({ id: 'throttle', tab: 'sign-ups', label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.throttle_heading') }))
    .setting(() => ({
      section: 'honeypot',
      setting: 'fof-anti-spam.honeypot.enabled',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_help'),
    }))
    .setting(() => ({
      section: 'honeypot',
      setting: 'fof-anti-spam.honeypot.minSeconds',
      type: 'number',
      min: 0,
      label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_min_seconds_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_min_seconds_help'),
    }))
    .setting(() => ({
      section: 'honeypot',
      setting: 'fof-anti-spam.honeypot.strict',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_strict_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.honeypot_strict_help'),
    }))
    .setting(() => ({
      section: 'throttle',
      setting: 'fof-anti-spam.registrationThrottleSeconds',
      type: 'number',
      min: 0,
      label: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.registration_throttle_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.sign_ups.registration_throttle_help'),
    }))

    // StopForumSpam
    .section(() => ({
      id: 'stopforumspam',
      tab: 'stopforumspam',
      label: 'StopForumSpam',
      description: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.introduction', {
        a: <Link href="https://stopforumspam.com" target="_blank" external={true} />,
      }),
    }))
    .section(() => ({
      id: 'sfs-checks',
      tab: 'stopforumspam',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.checks_heading'),
    }))
    .section(() => ({
      id: 'sfs-thresholds',
      tab: 'stopforumspam',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.thresholds_heading'),
    }))
    .section(() => ({
      id: 'sfs-also-block',
      tab: 'stopforumspam',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.also_block_heading'),
    }))
    .section(() => ({
      id: 'sfs-reporting',
      tab: 'stopforumspam',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.reporting_heading'),
      description: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_text'),
    }))
    .customSetting(
      function (this: AntiSpamSettingsPage) {
        return this.lookupFailureWarning();
      },
      0,
      'stopforumspam'
    )
    .setting(() => ({
      section: 'stopforumspam',
      setting: 'fof-anti-spam.sfs-lookup',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_help'),
    }))
    .setting(() => ({
      section: 'stopforumspam',
      setting: 'fof-anti-spam.regionalEndpoint',
      type: 'select',
      options: {
        closest: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.region_closest_label'),
        europe: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.region_europe_label'),
        us: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.region_us_label'),
      },
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_help'),
    }))
    .setting(() => ({
      section: 'sfs-checks',
      setting: 'fof-anti-spam.username',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.username_label'),
    }))
    .setting(() => ({
      section: 'sfs-checks',
      setting: 'fof-anti-spam.ip',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.ip_label'),
    }))
    .setting(() => ({
      section: 'sfs-checks',
      setting: 'fof-anti-spam.email',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_label'),
    }))
    .setting(() => ({
      section: 'sfs-checks',
      setting: 'fof-anti-spam.emailhash',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_hash_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_hash_help'),
    }))
    .setting(() => ({
      section: 'sfs-thresholds',
      setting: 'fof-anti-spam.frequency',
      type: 'number',
      placeholder: '5',
      required: true,
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_help'),
    }))
    .setting(() => ({
      section: 'sfs-thresholds',
      setting: 'fof-anti-spam.confidence',
      type: 'number',
      min: 0,
      max: 100,
      placeholder: '50.0',
      required: true,
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_help'),
    }))
    .setting(() => ({
      section: 'sfs-thresholds',
      setting: 'fof-anti-spam.maxListingAgeDays',
      type: 'number',
      min: 0,
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.max_listing_age_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.max_listing_age_help'),
    }))
    .setting(() => ({
      section: 'sfs-also-block',
      setting: 'fof-anti-spam.blockTorExitNodes',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.block_tor_exit_nodes_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.block_tor_exit_nodes_help'),
    }))
    .setting(() => ({
      section: 'sfs-also-block',
      setting: 'fof-anti-spam.blockedAsns',
      type: 'textarea',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.blocked_asns_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.blocked_asns_help'),
    }))
    .setting(() => ({
      section: 'sfs-reporting',
      setting: 'fof-anti-spam.api_key',
      type: 'string',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_instructions_text', {
        register: <a href="https://www.stopforumspam.com/forum/register.php" />,
        key: <a href="https://www.stopforumspam.com/keys" />,
      }),
    }))
    .setting(() => ({
      section: 'sfs-reporting',
      setting: 'fof-anti-spam.report_blocked_registrations',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_help'),
    }))

    // Content filter
    .section(() => ({
      id: 'content-filter',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.heading'),
      description: app.translator.trans('fof-anti-spam.admin.settings.content-filter.introduction'),
    }))
    .section(() => ({
      id: 'cf-user-targeting',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.user_targeting_heading'),
    }))
    .section(() => ({
      id: 'cf-held-email-domains',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.held_email_domains_heading'),
    }))
    .section(() => ({
      id: 'cf-detectors',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detectors_heading'),
    }))
    .section(() => ({
      id: 'cf-posting-rate',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.posting_rate_heading'),
    }))
    .section(() => ({
      id: 'cf-thresholds',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.thresholds_heading'),
    }))
    .section(() => ({
      id: 'cf-actions',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.actions_heading'),
    }))
    .section(() => ({
      id: 'cf-allowlist',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.allowlist_heading'),
    }))
    .section(() => ({
      id: 'cf-blocked-words',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.blocked_words_heading'),
    }))
    .section(() => ({
      id: 'cf-advanced-patterns',
      tab: 'content-filter',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.advanced_patterns_heading'),
    }))
    .setting(() => ({
      section: 'content-filter',
      setting: 'fof-anti-spam.content-filter.enabled',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.enabled_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.enabled_help'),
    }))
    .setting(() => ({
      section: 'cf-user-targeting',
      setting: 'fof-anti-spam.content-filter.monitor_all_users',
      type: 'switch',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_all_users_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_all_users_help'),
    }))
    .customSetting(
      function (this: AntiSpamSettingsPage) {
        return this.monitoringEveryone() && this.holdingEmailDomains() ? (
          <p className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_window_held_only')}</p>
        ) : null;
      },
      0,
      'cf-user-targeting'
    )
    .setting(() => ({
      section: 'cf-user-targeting',
      setting: 'fof-anti-spam.content-filter.monitor_post_count',
      type: 'number',
      placeholder: '5',
      min: 0,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_post_count_help'),
    }))
    .setting(() => ({
      section: 'cf-user-targeting',
      setting: 'fof-anti-spam.content-filter.monitor_hours_old',
      type: 'number',
      placeholder: '24',
      min: 0,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.monitor_hours_old_help'),
    }))
    .setting(() => ({
      section: 'cf-held-email-domains',
      setting: 'fof-anti-spam.content-filter.held_email_domains',
      type: 'textarea',
      placeholder: 'example.com\nexample.org',
      rows: 4,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.held_email_domains_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.held_email_domains_help'),
    }))
    .setting(() => ({
      section: 'cf-detectors',
      setting: 'fof-anti-spam.content-filter.detect_phones',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_phones_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_phones_help'),
    }))
    .setting(() => ({
      section: 'cf-detectors',
      setting: 'fof-anti-spam.content-filter.detect_local_phones',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_local_phones_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_local_phones_help'),
    }))
    .setting(() => ({
      section: 'cf-detectors',
      setting: 'fof-anti-spam.content-filter.detect_emails',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_emails_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_emails_help'),
    }))
    .setting(() => ({
      section: 'cf-detectors',
      setting: 'fof-anti-spam.content-filter.detect_urls',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_urls_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_urls_help'),
    }))
    .setting(() => ({
      section: 'cf-detectors',
      setting: 'fof-anti-spam.content-filter.detect_repeats',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_repeats_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.detect_repeats_help'),
    }))
    .setting(() => ({
      section: 'cf-posting-rate',
      setting: 'fof-anti-spam.content-filter.burst_post_count',
      type: 'number',
      placeholder: '5',
      min: 0,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.burst_post_count_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.burst_post_count_help'),
    }))
    .setting(() => ({
      section: 'cf-posting-rate',
      setting: 'fof-anti-spam.content-filter.burst_minutes',
      type: 'number',
      placeholder: '10',
      min: 1,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.burst_minutes_label'),
    }))
    .setting(() => ({
      section: 'cf-thresholds',
      setting: 'fof-anti-spam.content-filter.flag_threshold',
      type: 'number',
      placeholder: '50',
      min: 0,
      max: 100,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_threshold_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_threshold_help'),
    }))
    .setting(() => ({
      section: 'cf-thresholds',
      setting: 'fof-anti-spam.content-filter.spam_threshold',
      type: 'number',
      placeholder: '70',
      min: 0,
      max: 100,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.spam_threshold_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.spam_threshold_help'),
    }))
    .setting(() =>
      approvalEnabled()
        ? {
            section: 'cf-actions',
            setting: 'fof-anti-spam.content-filter.auto_unapprove',
            type: 'boolean',
            label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_unapprove_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_unapprove_help'),
          }
        : null
    )
    .setting(() =>
      flagsEnabled()
        ? {
            section: 'cf-actions',
            setting: 'fof-anti-spam.content-filter.auto_flag',
            type: 'boolean',
            label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_flag_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.auto_flag_help'),
          }
        : null
    )
    .setting(() =>
      flagsEnabled()
        ? {
            section: 'cf-actions',
            setting: 'fof-anti-spam.moderation.system_user_id',
            type: 'fof-anti-spam.flag-account',
            label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_help'),
          }
        : null
    )
    .customSetting(
      () =>
        !approvalEnabled() && !flagsEnabled() ? (
          <p className="helpText Alert">{app.translator.trans('fof-anti-spam.admin.settings.content-filter.no_action_extensions')}</p>
        ) : null,
      0,
      'cf-actions'
    )
    .setting(() => ({
      section: 'cf-allowlist',
      setting: 'fof-anti-spam.content-filter.allowed_domains',
      type: 'textarea',
      placeholder: 'example.com\nyoutube.com\ngithub.com',
      rows: 5,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.allowed_domains_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.allowed_domains_help'),
    }))
    .setting(() => ({
      section: 'cf-blocked-words',
      setting: 'fof-anti-spam.content-filter.blocked_words',
      type: 'textarea',
      placeholder: 'viagra\ncialis\ncrypto pump',
      rows: 8,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.blocked_words_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.blocked_words_help'),
    }))
    .setting(() => ({
      section: 'cf-advanced-patterns',
      setting: 'fof-anti-spam.content-filter.advanced_block_patterns',
      type: 'textarea',
      placeholder: '[{"pattern": "/\\\\b(viagra|cialis)\\\\b/i", "description": "Pharmaceutical spam"}]',
      rows: 5,
      label: app.translator.trans('fof-anti-spam.admin.settings.content-filter.advanced_block_patterns_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.content-filter.advanced_block_patterns_help'),
    }))

    // Spammer actions
    .section(() => ({
      id: 'default-actions',
      tab: 'spammer-actions',
      label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.heading'),
      description: app.translator.trans('fof-anti-spam.admin.settings.default-actions.introduction'),
    }))
    .setting(() => ({
      section: 'default-actions',
      setting: 'fof-anti-spam.actions.deleteUser',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_user_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_user_help'),
    }))
    .setting(() => ({
      section: 'default-actions',
      setting: 'fof-anti-spam.actions.deletePosts',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_posts_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_posts_help'),
    }))
    .setting(() => ({
      section: 'default-actions',
      setting: 'fof-anti-spam.actions.deleteDiscussions',
      type: 'boolean',
      label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_discussions_label'),
      help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_discussions_help'),
    }))
    .setting(() =>
      tagsEnabled()
        ? {
            section: 'default-actions',
            setting: 'fof-anti-spam.actions.moveDiscussionsToTags',
            type: 'flarum-tags.select-tags',
            label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.move_discussions_to_tags_label'),
            help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.move_discussions_to_tags_help'),
            options: {
              requireParentTag: true,
              limits: { max: { primary: 1 }, min: { primary: 1 } },
            },
          }
        : null
    )

    .permission(
      () => ({
        icon: 'fas fa-pastafarianism',
        label: app.translator.trans('fof-anti-spam.admin.permissions.spamblock_users_label'),
        permission: 'user.spamblock',
      }),
      'moderate'
    ),
];

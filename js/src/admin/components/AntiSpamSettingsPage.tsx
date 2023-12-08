import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import type Mithril from 'mithril';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import BlockedRegistration from '../../common/models/BlockedRegistration';
import ItemList from 'flarum/common/utils/ItemList';
import LabelValue from 'flarum/common/components/LabelValue';
import fullTime from 'flarum/common/helpers/fullTime';

export default class AntiSpamSettingsPage extends ExtensionPage {
  private static readonly ITEMS_PER_PAGE: number = 20;

  page!: string;
  blockedLoading: boolean = false;
  blockedRegistrations: BlockedRegistration[] | null | undefined = null;

  currentPage: number = 1;
  totalPages: number = 1;

  oninit(vnode: any) {
    super.oninit(vnode);

    this.page = 'settings';
  }

  content() {
    return (
      <div className="FoFAntiSpamSettings">
        <div className="container">
          {this.menuButtons()}
          {this.page === 'settings' && this.settingsContent()}
          {this.page === 'blocked-registrations' && this.blockedRegistrationsContent()}
        </div>
      </div>
    );
  }

  menuButtons(): Mithril.Children {
    return (
      <div className="MenuButtons">
        <Button className={`Button ${this.page === 'settings' ? 'active' : ''}`} icon="fas fa-cog" onclick={() => this.setPage('settings')}>
          {app.translator.trans('fof-anti-spam.admin.settings.button')}
        </Button>
        <Button
          className={`Button ${this.page === 'blocked-registrations' ? 'active' : ''}`}
          icon="fas fa-ban"
          onclick={() => this.setPage('blocked-registrations')}
        >
          {app.translator.trans('fof-anti-spam.admin.blocked_registrations.button')}
        </Button>
      </div>
    );
  }

  setPage(page: string): void {
    this.page = page;

    if (page === 'blocked-registrations' && !this.blockedRegistrations) {
      this.loadData();
    } else {
      m.redraw();
    }
  }

  settingsContent(): Mithril.Children {
    const apiRegions = ['closest', 'europe', 'us'];
    const tagsEnabled = app.initializers.has('flarum-tags');

    return (
      <div className="FoFAntiSpamSettings--settings">
        <div className="Form">
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
            <h3>{app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.heading')}</h3>
            <div className="Introduction">
              <p className="helpText">
                {app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.introduction', {
                  a: <Link href="https://stopforumspam.com" target="_blank" external={true} />,
                })}
              </p>
            </div>
            {this.buildSettingComponent({
              type: 'select',
              setting: 'fof-anti-spam.regionalEndpoint',
              options: apiRegions.reduce((o: { [key: string]: string }, p) => {
                o[p] = app.translator.trans(`fof-anti-spam.admin.settings.stopforumspam.region_${p}_label`) as string;
                return o;
              }, {}),
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_help'),
              default: 'closest',
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.sfs-lookup',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_help'),
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
        </div>
      </div>
    );
  }

  blockedRegistrationsContent(): Mithril.Children {
    return (
      <div className="FoFAntiSpamSettings--blockedRegistrations">
        <div className="Form">
          <h3>{app.translator.trans('fof-anti-spam.admin.blocked_registrations.title')}</h3>
          {this.blockedLoading && <LoadingIndicator />}
          {!this.blockedLoading && this.blockedRegistrations && this.blockedRegistrations.length === 0 && (
            <div>
              <p>{app.translator.trans('fof-anti-spam.admin.blocked_registrations.no-records')}</p>
            </div>
          )}
          {!this.blockedLoading && this.blockedRegistrations && this.blockedRegistrations.length > 0 && (
            <div>
              <p className="helpText">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.help')}</p>
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
              {this.renderPagination()}
            </div>
          )}
        </div>
      </div>
    );
  }

  async loadData(page: number = 1) {
    this.blockedLoading = true;
    m.redraw();

    try {
      const response = await app.store.find<BlockedRegistration[]>('blocked-registrations', {
        page: {
          offset: (page - 1) * AntiSpamSettingsPage.ITEMS_PER_PAGE,
          limit: AntiSpamSettingsPage.ITEMS_PER_PAGE,
        },
      });

      this.blockedRegistrations = response;
      this.totalPages = response.payload.links?.totalPages || 1;
    } catch (error) {
      console.error(error);
      this.blockedRegistrations = [];
    }

    this.blockedLoading = false;
    this.currentPage = page;
    m.redraw();
  }

  renderPagination(): Mithril.Children {
    return (
      <nav className="BlockedRegistrations--pagination">
        <Button className="Button" disabled={this.currentPage <= 1} onclick={() => this.loadData(this.currentPage - 1)}>
          Previous
        </Button>
        <span>
          Page {this.currentPage} of {this.totalPages}
        </span>
        <Button className="Button" disabled={this.currentPage >= this.totalPages} onclick={() => this.loadData(this.currentPage + 1)}>
          Next
        </Button>
      </nav>
    );
  }

  detailItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'attemptedAt',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.attempted-at')}</span>
        <span className="BlockedRegistrations-value">{fullTime(blockedRegistration.attemptedAt() ?? new Date())}</span>
      </div>,
      100
    );

    items.add(
      'ip',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.ip')}</span>
        <span className="BlockedRegistrations-value">{blockedRegistration.ip()}</span>
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

    if (blockedRegistration.providerData()) {
      items.add(
        'providerData',
        <div className="BlockedRegistrations-item--details">
          <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.login-provider-data')}</span>
          <span className="BlockedRegistrations-value">
            <code>{blockedRegistration.providerData()}</code>
          </span>
        </div>,
        50
      );
    }

    items.add(
      'sfsData',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.sfs-data')}</span>
        <span className="BlockedRegistrations-value">
          <code>{blockedRegistration.sfsData()}</code>
        </span>
      </div>,
      20
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

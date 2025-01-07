import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import BlockedRegistration from '../../common/models/BlockedRegistration';
import ItemList from 'flarum/common/utils/ItemList';
import Button from 'flarum/common/components/Button';
import fullTime from 'flarum/common/helpers/fullTime';
import AntiSpamSettingsPage from './AntiSpamSettingsPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import BlockedRegistrationValueItem from './BlockedRegistrationValueItem';

interface CustomAttrs extends ComponentAttrs {}

export default class BlockedRegistrationsPage extends Component<CustomAttrs> {
  blockedLoading: boolean = true;
  blockedRegistrations: BlockedRegistration[] = [];

  currentPage: number = 1;
  totalPages: number = 1;

  oninit(vnode: Mithril.Vnode<CustomAttrs, this>) {
    super.oninit(vnode);

    this.loadData();
  }

  view(): Mithril.Children {
    return (
      <div className="FoFAntiSpamTabPage FoFAntiSpamSettings--blockedRegistrations">
        <div className="Form">
          <h3>{app.translator.trans('fof-anti-spam.admin.blocked_registrations.title')}</h3>
          <p className="helpText">{app.translator.trans('fof-anti-spam.admin.blocked_registrations.help')}</p>
          {this.blockedLoading ? (
            <LoadingIndicator />
          ) : this.blockedRegistrations.length === 0 ? (
            <div>
              <p>{app.translator.trans('fof-anti-spam.admin.blocked_registrations.no-records')}</p>
            </div>
          ) : (
            this.blockedRegistrations.length > 0 && (
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
                {this.renderPagination()}
              </div>
            )
          )}
        </div>
      </div>
    );
  }

  detailItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'attemptedAt',
      <BlockedRegistrationValueItem
        label={app.translator.trans('fof-anti-spam.admin.blocked_registrations.attempted-at')}
        value={fullTime(blockedRegistration.attemptedAt() ?? new Date())}
      />,
      100
    );

    items.add(
      'ip',
      <BlockedRegistrationValueItem label={app.translator.trans('fof-anti-spam.admin.blocked_registrations.ip')} value={blockedRegistration.ip()} />,
      90
    );

    items.add(
      'email',
      <BlockedRegistrationValueItem
        label={app.translator.trans('fof-anti-spam.admin.blocked_registrations.email')}
        value={blockedRegistration.email()}
      />,
      80
    );

    items.add(
      'username',
      <BlockedRegistrationValueItem
        label={app.translator.trans('fof-anti-spam.admin.blocked_registrations.username')}
        value={blockedRegistration.username()}
      />,
      70
    );

    if (blockedRegistration.provider()) {
      items.add(
        'provider',
        <BlockedRegistrationValueItem
          label={app.translator.trans('fof-anti-spam.admin.blocked_registrations.login-provider')}
          value={<code>{blockedRegistration.provider()}</code>}
        />,
        60
      );
    }

    if (blockedRegistration.providerData()) {
      items.add(
        'providerData',
        <BlockedRegistrationValueItem
          label={app.translator.trans('fof-anti-spam.admin.blocked_registrations.login-provider-data')}
          value={<code>{blockedRegistration.providerData()}</code>}
        />,
        50
      );
    }

    items.add(
      'sfsData',
      <BlockedRegistrationValueItem
        label={app.translator.trans('fof-anti-spam.admin.blocked_registrations.sfs-data')}
        value={<code>{blockedRegistration.sfsData()}</code>}
      />,
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
}

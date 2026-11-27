import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Form from 'flarum/common/components/Form';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import IPAddress from 'flarum/common/components/IPAddress';
import Pagination from 'flarum/common/components/Pagination';
import fullTime from 'flarum/common/helpers/fullTime';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import BlockedRegistration from '../../common/models/BlockedRegistration';
import BlockedRegistrationSearch from './BlockedRegistrationSearch';
import BlockEvidenceSummary from './BlockEvidenceSummary';
import parseBlockedRegistrationQuery from '../utils/parseBlockedRegistrationQuery';

export default class BlockedRegistrationList extends Component {
  static readonly ITEMS_PER_PAGE: number = 20;

  blockedLoading: boolean = false;
  blockedRegistrations: BlockedRegistration[] | null | undefined = null;

  currentPage: number = 1;
  /** Total matching records, from the API's page meta — not a page count. */
  total: number = 0;

  /** The raw `key:value` search query, parsed into filters when the request is made. */
  query: string = '';

  /** Total with no filters applied, to tell "nothing recorded" from "nothing matched". */
  totalUnfiltered: number = 0;

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    this.loadData();
  }

  view(): Mithril.Children {
    return (
      <div className="FoFAntiSpamSettings FoFAntiSpamSettings--blockedRegistrations">
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
          offset: (page - 1) * BlockedRegistrationList.ITEMS_PER_PAGE,
          limit: BlockedRegistrationList.ITEMS_PER_PAGE,
        },
      });

      this.blockedRegistrations = response;

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
   * Taken from unfiltered loads, so a query that matches nothing cannot make the page claim the
   * forum has never blocked a registration.
   */
  hasRecords(): boolean {
    return this.totalUnfiltered > 0;
  }

  hasActiveFilters(): boolean {
    return this.query !== '';
  }

  renderPagination(): Mithril.Children {
    if (this.total <= BlockedRegistrationList.ITEMS_PER_PAGE) return null;

    return (
      <Pagination
        currentPage={this.currentPage}
        loadingPageNumber={this.blockedLoading ? this.currentPage : undefined}
        total={this.total}
        perPage={BlockedRegistrationList.ITEMS_PER_PAGE}
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
        {/* No fallback to now: a missing date would pass for a block seconds ago. */}
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

    items.add(
      'rawData',
      <div className="BlockedRegistrations-item--details">
        <span className="BlockedRegistrations-label" />
        <span className="BlockedRegistrations-value">
          {/* Loaded on demand, from the jsDirectory registered in extend.php. */}
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

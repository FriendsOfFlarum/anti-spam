import app from 'flarum/admin/app';
import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import abbreviateNumber from 'flarum/common/utils/abbreviateNumber';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

const PREFIX = 'fof-anti-spam.admin.statistics';

interface Measure {
  total: number;
  period: number;
  previousPeriod: number;
}

interface LifetimeStats {
  registrationsBlocked: Measure;
  /** Only when flarum/flags is enabled — it is what records the content filter's flags. */
  postsFlagged?: Measure;
  /** Only when flarum/audit is enabled — spamblocks are not stored by this extension. */
  usersMarkedAsSpammers?: Measure;
  byReason: Record<string, number>;
  byProvider: Record<string, number>;
}

/**
 * How the forum is holding up against spam.
 *
 * Reports each of the extension's three defences — registrations turned away, posts the content
 * filter caught, users marked as spammers afterwards — each against the previous week, because
 * a raw count says nothing on its own about whether things are improving.
 *
 * The latter two are only shown when the extension that records them is enabled.
 */
export default class BlockedRegistrationsWidget extends DashboardWidget {
  lifetime: LifetimeStats | null = null;

  loading = true;
  failed = false;

  oncreate(vnode: Mithril.VnodeDOM<IDashboardWidgetAttrs, this>) {
    super.oncreate(vnode);

    this.load();
  }

  async load() {
    this.loading = true;
    this.failed = false;
    m.redraw();

    try {
      this.lifetime = await app.request<LifetimeStats>({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/fof/anti-spam/statistics`,
        params: { period: 'lifetime' },
      });
    } catch (e) {
      // A widget that cannot load its data should say so, not sit on a spinner forever.
      this.failed = true;
    }

    this.loading = false;
    m.redraw();
  }

  className() {
    return 'StatisticsWidget StatisticsWidget--mini BlockedRegistrationsWidget';
  }

  content() {
    return (
      <div className="StatisticsWidget-table">
        <h4 className="StatisticsWidget-title">
          <Icon name="fas fa-shield-alt" />
          {app.translator.trans(`${PREFIX}.heading`)}
        </h4>

        {this.failed ? this.error() : this.figures()}

        <div className="StatisticsWidget-viewFull">
          <Link href={app.route('extension', { id: 'fof-anti-spam', page: 'blocked-registrations' })}>
            {app.translator.trans(`${PREFIX}.view_all`)}
          </Link>
        </div>
      </div>
    );
  }

  private error(): Mithril.Children {
    return <p className="BlockedRegistrationsWidget-error">{app.translator.trans(`${PREFIX}.failed`)}</p>;
  }

  private figures(): Mithril.Children {
    const stats = this.lifetime;

    return (
      <div className="BlockedRegistrationsWidget-measures">
        {this.measure(`${PREFIX}.registrations_blocked`, stats?.registrationsBlocked)}
        {stats?.usersMarkedAsSpammers && this.measure(`${PREFIX}.users_marked`, stats.usersMarkedAsSpammers)}
        {stats?.postsFlagged && this.measure(`${PREFIX}.posts_flagged`, stats.postsFlagged)}
        {this.topReason()}
      </div>
    );
  }

  /**
   * One metric: its all-time total, the last seven days, and how that compares with the seven
   * days before.
   */
  private measure(key: string, measure?: Measure): Mithril.Children {
    return (
      <div className="BlockedRegistrationsWidget-measure">
        <h3 className="BlockedRegistrationsWidget-label">{app.translator.trans(key)}</h3>

        <div className="BlockedRegistrationsWidget-figures">
          <span className="BlockedRegistrationsWidget-total" title={String(measure?.total ?? 0)}>
            {this.loading ? <LoadingIndicator display="inline" /> : abbreviateNumber(measure?.total ?? 0)}
          </span>

          {!this.loading && (
            <span className="BlockedRegistrationsWidget-period">
              {app.translator.trans(`${PREFIX}.this_week`, { count: measure?.period ?? 0 })}
              {this.change(measure)}
            </span>
          )}
        </div>
      </div>
    );
  }

  /**
   * The week-on-week change.
   *
   * Rising blocks are not straightforwardly bad news — they can mean more spam arriving as
   * easily as more getting through — so this is coloured neutrally and left for the admin to
   * read, rather than being dressed up as good or bad.
   */
  private change(measure?: Measure): Mithril.Children {
    if (!measure || measure.previousPeriod === 0) return null;

    const delta = Math.round(((measure.period - measure.previousPeriod) / measure.previousPeriod) * 100);

    if (delta === 0) return null;

    return (
      <span className="BlockedRegistrationsWidget-change" title={extractText(app.translator.trans(`${PREFIX}.vs_last_week`))}>
        <Icon name={delta > 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'} />
        {Math.abs(delta)}%
      </span>
    );
  }

  /**
   * The rule doing the most work — worth knowing before an admin touches their thresholds.
   */
  private topReason(): Mithril.Children {
    const reasons = this.lifetime?.byReason ?? {};
    // 'unrecorded' counts rows blocked before reasons were recorded; it is not a rule, and
    // must not be presented as the reason registrations are being turned away.
    const ranked = Object.entries(reasons).filter(([reason]) => reason !== 'unrecorded');

    if (this.loading || !ranked.length) return null;

    const [reason, count] = ranked.reduce((top, entry) => (entry[1] > top[1] ? entry : top));

    return (
      <p className="BlockedRegistrationsWidget-topReason">
        {app.translator.trans(`${PREFIX}.top_reason`, {
          reason: app.translator.trans(`fof-anti-spam.admin.blocked_registrations.evidence.rule.${reason}`),
          count,
        })}
      </p>
    );
  }
}

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
  /**
   * Spam flags currently open — the moderator's queue, not a tally. flarum/flags deletes a
   * flag when it is dismissed and when its post is deleted, so this cannot be a total.
   */
  flagsAwaitingReview: number;
  /** Durable counts, from the audit log; only when flarum/audit is enabled. */
  usersMarkedAsSpammers?: Measure;
  postsFlagged?: Measure;
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

    // Core's own structure — labels column, then a row of entities — so this sits in the
    // dashboard looking like the statistics widget above it rather than near it.
    return [
      <div className="StatisticsWidget-entities">
        <div className="StatisticsWidget-labels">
          <div className="StatisticsWidget-label">{app.translator.trans(`${PREFIX}.total_label`)}</div>
        </div>

        <div className="StatisticsWidget-entityList">
          {this.measure(`${PREFIX}.registrations_blocked`, stats?.registrationsBlocked)}
          {stats?.usersMarkedAsSpammers && this.measure(`${PREFIX}.users_marked`, stats.usersMarkedAsSpammers)}
          {stats?.postsFlagged && this.measure(`${PREFIX}.posts_flagged`, stats.postsFlagged)}
          {this.awaitingReview()}
        </div>
      </div>,
      this.topReason(),
    ];
  }

  /**
   * One metric: its all-time total, the last seven days, and how that compares with the seven
   * days before.
   */
  private measure(key: string, measure?: Measure): Mithril.Children {
    const total = measure?.total ?? 0;

    return (
      <div className="StatisticsWidget-entity">
        <h3 className="StatisticsWidget-heading">{app.translator.trans(key)}</h3>

        <div className="StatisticsWidget-total" title={String(total)}>
          {this.loading ? <LoadingIndicator display="inline" /> : abbreviateNumber(total)}
        </div>

        {!this.loading && (
          <div className="StatisticsWidget-period">
            {app.translator.trans(`${PREFIX}.this_week`, { count: measure?.period ?? 0 })} {this.change(measure)}
          </div>
        )}
      </div>
    );
  }

  /**
   * Spam flags still open.
   *
   * No total and no trend: the rows behind this are deleted on dismissal, so both would say
   * more about how quickly moderators work than about how much spam is arriving.
   */
  private awaitingReview(): Mithril.Children {
    const open = this.lifetime?.flagsAwaitingReview ?? 0;

    if (this.loading || open === 0) return null;

    return (
      <div className="StatisticsWidget-entity">
        <h3 className="StatisticsWidget-heading">{app.translator.trans(`${PREFIX}.awaiting_review`)}</h3>

        <div className="StatisticsWidget-total" title={String(open)}>
          {abbreviateNumber(open)}
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

    // Core's -change classes colour up green and down red, which reads as good/bad news. That
    // is wrong here: more blocks can mean more spam arriving as easily as more getting through,
    // so the direction is shown without the judgement.
    return (
      <span className="StatisticsWidget-change" title={extractText(app.translator.trans(`${PREFIX}.vs_last_week`))}>
        <Icon name={delta > 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'} /> {Math.abs(delta)}%
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

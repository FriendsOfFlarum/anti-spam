import app from 'flarum/admin/app';
import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import abbreviateNumber from 'flarum/common/utils/abbreviateNumber';
import type Mithril from 'mithril';

const PREFIX = 'fof-anti-spam.admin.statistics';

interface LifetimeStats {
  total: number;
  byReason: Record<string, number>;
  byProvider: Record<string, number>;
}

/**
 * Blocked registrations on the admin dashboard, alongside flarum/statistics' own widget.
 *
 * This is a widget of our own rather than an entity added to flarum/statistics: that
 * extension's entity list is a hardcoded private array, and its endpoint rejects any model it
 * does not already know, so there is nothing for an extension to hook into.
 */
export default class BlockedRegistrationsWidget extends DashboardWidget {
  lifetime: LifetimeStats | null = null;
  timed: Record<string, number> | null = null;

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
      const [lifetime, timed] = await Promise.all([
        app.request<LifetimeStats>({
          method: 'GET',
          url: `${app.forum.attribute('apiUrl')}/fof/anti-spam/statistics`,
          params: { period: 'lifetime' },
        }),
        app.request<Record<string, number>>({
          method: 'GET',
          url: `${app.forum.attribute('apiUrl')}/fof/anti-spam/statistics`,
          params: { period: 'timed' },
        }),
      ]);

      this.lifetime = lifetime;
      this.timed = timed;
    } catch (e) {
      // A dashboard widget that cannot load its data should say so, not sit on a spinner
      // forever or vanish and leave the admin wondering where it went.
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
          <Icon name="fas fa-ban" />
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
    return (
      <div className="StatisticsWidget-entities">
        <div className="StatisticsWidget-labels">
          <div className="StatisticsWidget-label">{app.translator.trans(`${PREFIX}.total_label`)}</div>
        </div>

        <div className="StatisticsWidget-entityList">
          {this.figure(`${PREFIX}.blocked_heading`, this.lifetime?.total ?? 0)}
          {this.figure(`${PREFIX}.last_24h_heading`, this.countSince(24 * 60 * 60))}
          {this.figure(`${PREFIX}.last_7d_heading`, this.countSince(7 * 24 * 60 * 60))}
        </div>

        {this.topReason()}
      </div>
    );
  }

  private figure(key: string, count: number): Mithril.Children {
    return (
      <div className="StatisticsWidget-entity">
        <h3 className="StatisticsWidget-heading">{app.translator.trans(key)}</h3>
        <div className="StatisticsWidget-total" title={String(count)}>
          {this.loading ? <LoadingIndicator display="inline" /> : abbreviateNumber(count)}
        </div>
      </div>
    );
  }

  /**
   * The buckets are keyed by unix timestamp, so a period is the sum of everything at or after
   * its start.
   */
  private countSince(seconds: number): number {
    if (!this.timed) return 0;

    const cutoff = Date.now() / 1000 - seconds;

    return Object.entries(this.timed).reduce((total, [timestamp, count]) => (Number(timestamp) >= cutoff ? total + count : total), 0);
  }

  /**
   * The rule doing the most work. Useful at a glance: if one rule accounts for nearly
   * everything, that is worth an admin knowing before they touch their thresholds.
   */
  private topReason(): Mithril.Children {
    const reasons = this.lifetime?.byReason ?? {};
    // 'unrecorded' is not a rule — it is the rows we have no answer for — so it must not be
    // presented as the reason registrations are being blocked.
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

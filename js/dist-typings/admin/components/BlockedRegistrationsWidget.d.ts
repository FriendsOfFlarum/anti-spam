import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import type Mithril from 'mithril';
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
    lifetime: LifetimeStats | null;
    loading: boolean;
    failed: boolean;
    oncreate(vnode: Mithril.VnodeDOM<IDashboardWidgetAttrs, this>): void;
    load(): Promise<void>;
    className(): string;
    content(): JSX.Element;
    private error;
    private figures;
    /**
     * One metric: its all-time total, the last seven days, and how that compares with the seven
     * days before.
     */
    private measure;
    /**
     * Spam flags still open.
     *
     * No total and no trend: the rows behind this are deleted on dismissal, so both would say
     * more about how quickly moderators work than about how much spam is arriving.
     */
    private awaitingReview;
    /**
     * The week-on-week change.
     *
     * Rising blocks are not straightforwardly bad news — they can mean more spam arriving as
     * easily as more getting through — so this is coloured neutrally and left for the admin to
     * read, rather than being dressed up as good or bad.
     */
    private change;
    /**
     * The rule doing the most work — worth knowing before an admin touches their thresholds.
     */
    private topReason;
}
export {};

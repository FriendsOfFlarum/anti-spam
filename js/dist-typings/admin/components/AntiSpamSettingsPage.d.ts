import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type Mithril from 'mithril';
import BlockedRegistration from '../../common/models/BlockedRegistration';
import ItemList from 'flarum/common/utils/ItemList';
export default class AntiSpamSettingsPage extends ExtensionPage {
    private static readonly ITEMS_PER_PAGE;
    blockedLoading: boolean;
    blockedRegistrations: BlockedRegistration[] | null | undefined;
    currentPage: number;
    /** Total matching records, from the API's page meta — not a page count. */
    total: number;
    /** The raw `key:value` search query, parsed into filters when the request is made. */
    query: string;
    /** Total with no filters applied, to tell "nothing recorded" from "nothing matched". */
    totalUnfiltered: number;
    static register(): void;
    /**
     * A lookup that cannot reach StopForumSpam lets the registration through. That is the right
     * call — better an open door than a forum nobody can join — but it must not happen quietly, or
     * an admin cannot tell a working forum from one that has been checking nothing for a week.
     */
    lookupFailureWarning(): JSX.Element | null;
    oninit(vnode: Mithril.Vnode<any, this>): void;
    content(): JSX.Element;
    menuButtons(page: string): Mithril.Children;
    settingsContent(): Mithril.Children;
    blockedRegistrationsContent(): Mithril.Children;
    loadData(page?: number): Promise<void>;
    /**
     * Whether the table holds anything at all, as opposed to the current query matching nothing.
     * Recorded from the first unfiltered load so a query that matches nothing cannot make the
     * page claim the forum has never blocked a registration.
     */
    hasRecords(): boolean;
    /**
     * Whether every user is being monitored, in which case the post-count and account-age
     * windows no longer narrow anything.
     */
    monitoringEveryone(): boolean;
    hasActiveFilters(): boolean;
    renderPagination(): Mithril.Children;
    detailItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children>;
    actionItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children>;
}

import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type Mithril from 'mithril';
import BlockedRegistration from '../../common/models/BlockedRegistration';
import ItemList from 'flarum/common/utils/ItemList';
import type { GeneralIndexItem } from 'flarum/admin/states/GeneralSearchIndex';
export type AntiSpamTab = {
    icon: string;
    label: Mithril.Children;
    content: () => Mithril.Children;
};
export default class AntiSpamSettingsPage extends ExtensionPage {
    private static readonly ITEMS_PER_PAGE;
    /** Shown when the URL names no tab, or one that no longer exists. */
    static readonly DEFAULT_TAB: string;
    blockedLoading: boolean;
    blockedRegistrations: BlockedRegistration[] | null | undefined;
    currentPage: number;
    /** Total matching records, from the API's page meta — not a page count. */
    total: number;
    /** The raw `key:value` search query, parsed into filters when the request is made. */
    query: string;
    /** Total with no filters applied, to tell "nothing recorded" from "nothing matched". */
    totalUnfiltered: number;
    /**
     * Every setting, for the admin search. `tree` names the tab, because a result opens the first
     * tab whatever its `link` says: core does not read an item's own link yet.
     */
    static searchIndex(): GeneralIndexItem[];
    /**
     * A lookup that cannot reach StopForumSpam lets the registration through. That is the right
     * call — better an open door than a forum nobody can join — but it must not happen quietly, or
     * an admin cannot tell a working forum from one that has been checking nothing for a week.
     */
    lookupFailureWarning(): JSX.Element | null;
    oninit(vnode: Mithril.Vnode<any, this>): void;
    /**
     * Each tab is a `page` route parameter, so it survives a reload and can be linked to.
     */
    tabs(): ItemList<AntiSpamTab>;
    currentTab(tabs: ItemList<AntiSpamTab>): string;
    content(): JSX.Element;
    menuButtons(tabs: ItemList<AntiSpamTab>, current: string): Mithril.Children;
    /**
     * Moving to another tab rebuilds the page, which would otherwise drop unsaved edits without a word.
     */
    confirmLeave(e: MouseEvent): void;
    /**
     * Read the way core's Switch reads it: the stored value is the string '0' once switched off.
     */
    honeypotOn(): boolean;
    /**
     * Every settings tab saves on its own. The page is rebuilt for each tab, so only the settings
     * shown here can be waiting to save.
     */
    settingsForm(section: Mithril.Children): Mithril.Children;
    signUpsContent(): Mithril.Children;
    stopForumSpamContent(): Mithril.Children;
    contentFilterContent(): Mithril.Children;
    spammerActionsContent(): Mithril.Children;
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

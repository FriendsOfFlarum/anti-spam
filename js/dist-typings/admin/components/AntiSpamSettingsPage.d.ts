import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type Mithril from 'mithril';
import BlockedRegistration from '../../common/models/BlockedRegistration';
import ItemList from 'flarum/common/utils/ItemList';
export default class AntiSpamSettingsPage extends ExtensionPage {
    private static readonly ITEMS_PER_PAGE;
    page: string;
    blockedLoading: boolean;
    blockedRegistrations: BlockedRegistration[] | null | undefined;
    currentPage: number;
    totalPages: number;
    static register(): void;
    oninit(vnode: any): void;
    /**
     * A lookup that cannot reach StopForumSpam lets the registration through. That is the right
     * call — better an open door than a forum nobody can join — but it must not happen quietly, or
     * an admin cannot tell a working forum from one that has been checking nothing for a week.
     */
    lookupFailureWarning(): JSX.Element | null;
    content(): JSX.Element;
    menuButtons(): Mithril.Children;
    setPage(page: string): void;
    settingsContent(): Mithril.Children;
    blockedRegistrationsContent(): Mithril.Children;
    loadData(page?: number): Promise<void>;
    renderPagination(): Mithril.Children;
    detailItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children>;
    actionItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children>;
}

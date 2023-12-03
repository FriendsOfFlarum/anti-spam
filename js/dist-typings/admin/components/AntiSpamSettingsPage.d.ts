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
    oninit(vnode: any): void;
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

import Component, { type ComponentAttrs } from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import BlockedRegistration from '../../common/models/BlockedRegistration';
export interface IBlockedRegistrationListAttrs extends ComponentAttrs {
}
export default class BlockedRegistrationList extends Component<IBlockedRegistrationListAttrs> {
    static readonly ITEMS_PER_PAGE: number;
    blockedLoading: boolean;
    blockedRegistrations: BlockedRegistration[] | null | undefined;
    currentPage: number;
    /** Total matching records, from the API's page meta — not a page count. */
    total: number;
    /** The raw `key:value` search query, parsed into filters when the request is made. */
    query: string;
    /** Total with no filters applied, to tell "nothing recorded" from "nothing matched". */
    totalUnfiltered: number;
    oninit(vnode: Mithril.Vnode<IBlockedRegistrationListAttrs, this>): void;
    view(): Mithril.Children;
    loadData(page?: number): Promise<void>;
    /**
     * Whether the table holds anything at all, as opposed to the current query matching nothing.
     * Taken from unfiltered loads, so a query that matches nothing cannot make the page claim the
     * forum has never blocked a registration.
     */
    hasRecords(): boolean;
    hasActiveFilters(): boolean;
    renderPagination(): Mithril.Children;
    detailItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children>;
    actionItems(blockedRegistration: BlockedRegistration): ItemList<Mithril.Children>;
}

import Component, { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface BlockedRegistrationFilter {
    key: string;
    example: string;
    description: string | null;
    values: string[];
    extension: string | null;
}
export interface BlockedRegistrationSearchAttrs extends ComponentAttrs {
    /** The current query, so the parent owns the state. */
    query: string;
    loading: boolean;
    onsubmit: (query: string) => void;
}
/**
 * A `key:value` search box for the blocked registrations list.
 *
 * Modelled on flarum/audit's AuditBrowser: one input rather than a row of dropdowns, with the
 * available filters advertised by the backend so the syntax is discoverable — clickable chips,
 * a help panel, and value autocomplete for the filters with a known value set.
 *
 * @see AuditBrowser in flarum/audit
 */
export default class BlockedRegistrationSearch extends Component<BlockedRegistrationSearchAttrs> {
    q: string;
    caret: number;
    showHelp: boolean;
    autocompleteFocused: boolean;
    oninit(vnode: Mithril.Vnode<BlockedRegistrationSearchAttrs, this>): void;
    filters(): BlockedRegistrationFilter[];
    view(): Mithril.Children;
    submit(): void;
    /**
     * The whitespace-delimited token the caret currently sits in.
     */
    activeToken(): {
        start: number;
        end: number;
        text: string;
    };
    /**
     * Value suggestions for the filter being typed, for those that advertise a value set.
     */
    autocompleteValues(): {
        key: string;
        values: string[];
    } | null;
    autocomplete(): Mithril.Children;
    applySuggestion(key: string, value: string): void;
    filterHints(): Mithril.Children;
    filterHelp(filters: BlockedRegistrationFilter[]): Mithril.Children;
    /**
     * Filters advertising a value set open their suggestions. The rest prime the box with the
     * key and wait: their values are whatever the admin has in their data, so running a search
     * on a placeholder would only ever return nothing.
     */
    filterChip(filter: BlockedRegistrationFilter): Mithril.Children;
    /**
     * Run a complete `key:value` query straight away. Only used from the help panel's value
     * chips, where the value is one the backend advertises as real.
     */
    applyExample(example: string): void;
    openAutocomplete(key: string): void;
    private focusInput;
}

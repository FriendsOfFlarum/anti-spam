import ExtensionPage, { type ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import type { SettingsComponentOptions } from 'flarum/admin/components/AdminPage';
import type { TabItem } from 'flarum/common/components/Tabs';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
export default class AntiSpamSettingsPage extends ExtensionPage {
    static readonly BLOCKED_REGISTRATIONS_TAB = "blocked-registrations";
    /** Read from the unsaved values. */
    static readonly shownWhen: Record<string, (page: AntiSpamSettingsPage) => boolean>;
    oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>): void;
    pageTabItems(): ItemList<TabItem>;
    pageTabContent(tab: string, vnode: Mithril.VnodeDOM<ExtensionPageAttrs, this>): Mithril.Children;
    buildSettingComponent(entry: ((this: this) => Mithril.Children) | SettingsComponentOptions): Mithril.Children;
    /**
     * Lookups fail open, so the admin needs to know when sign-ups have been going unchecked.
     */
    lookupFailureWarning(): Mithril.Children;
    /**
     * Read the way core's Switch reads it: the stored value is the string '0' once switched off.
     */
    honeypotOn(): boolean;
    /**
     * Whether every user is being monitored, in which case the post-count and account-age
     * windows no longer narrow anything.
     */
    monitoringEveryone(): boolean;
    /**
     * Whether any email domain is held in the admin panel. Domains held in code are not known here.
     */
    holdingEmailDomains(): boolean;
}

import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type User from 'flarum/common/models/User';
import type Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
/**
 * The account automatic spam flags are raised by, as the backend resolves it.
 */
export interface FlagAccountPayload {
    id: string | null;
    displayName: string | null;
    username: string | null;
    avatarUrl: string | null;
    /** No account chosen, or the chosen one is gone: the first administrator raises flags. */
    isDefault: boolean;
    configuredMissing: boolean;
    chosenInCode: boolean;
}
export interface IFlagAccountSettingAttrs extends ComponentAttrs {
    /** The stored user ID; empty means the default. */
    setting: Stream<string>;
}
/**
 * Chooses the account automatic spam flags are raised by, by name rather than by user ID.
 */
export default class FlagAccountSetting extends Component<IFlagAccountSettingAttrs> {
    protected saved: FlagAccountPayload | null;
    protected savedValue: string;
    /** The account shown: the saved one until another is picked. */
    protected user: User | null;
    /** The first administrator, when the saved state tells us who that is. */
    protected defaultUser: User | null;
    oninit(vnode: Mithril.Vnode<IFlagAccountSettingAttrs, this>): void;
    view(): Mithril.Children;
    protected choose(): void;
}

import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import User from 'flarum/common/models/User';
import type Mithril from 'mithril';
interface HandleSpammerModalAttrs extends IInternalModalAttrs {
    user: User;
}
export default class HandleSpammerModal extends Modal<HandleSpammerModalAttrs> {
    user: User;
    hardDeleteUser: boolean;
    hardDeleteDiscussions: boolean;
    hardDeletePosts: boolean;
    moveDiscussionsToQuarantine: boolean;
    reportToSfs: boolean;
    oninit(vnode: Mithril.Vnode<HandleSpammerModalAttrs>): void;
    className(): string;
    title(): any[];
    content(): JSX.Element;
    submitData(): void;
}
export {};

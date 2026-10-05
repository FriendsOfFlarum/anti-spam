import FormModal, { IFormModalAttrs } from 'flarum/common/components/FormModal';
import User from 'flarum/common/models/User';
import type Mithril from 'mithril';
import Stream from 'flarum/common/utils/Stream';
interface HandleSpammerModalAttrs extends IFormModalAttrs {
    user: User;
}
export default class HandleSpammerModal extends FormModal<HandleSpammerModalAttrs> {
    user: User;
    hardDeleteUser: Stream<boolean>;
    hardDeleteDiscussions: Stream<boolean>;
    hardDeletePosts: Stream<boolean>;
    moveDiscussionsToQuarantine: Stream<boolean>;
    reportToSfs: Stream<boolean>;
    oninit(vnode: Mithril.Vnode<HandleSpammerModalAttrs>): void;
    className(): string;
    title(): any[];
    content(): JSX.Element;
    onsubmit(event: SubmitEvent): void;
}
export {};

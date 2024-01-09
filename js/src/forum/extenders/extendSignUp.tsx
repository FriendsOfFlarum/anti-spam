import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import SignUpModal from 'flarum/forum/components/SignUpModal';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import Button from 'flarum/common/components/Button';
import AskChallengeQuestionModal from '../components/AskChallengeQuestionModal';
import ChallengeQuestionField from '../components/ChallengeQuestionField';

export default function extendSignUp() {
  extend(SignUpModal.prototype, 'fields', function (fields) {
    if (app.forum.attribute('fof-anti-spam.challenge')) {
      fields.add('challenge-question', <ChallengeQuestionField />, 8);
    }
  });
}
// fields.setContent(
//     'submit',
//     <Button className="Button Button--primary Button--block" onclick={() => app.modal.show(AskChallengeQuestionModal, {onChallengeComplete: this.onChallengeComplete.bind(this)}, true)}>
//         {app.translator.trans('core.forum.sign_up.submit_button')}
//     </Button>
// );

// SignUpModal.prototype.onChallengeComplete = function () {
//     console.log('challenge complete');
// }

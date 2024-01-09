import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import AskChallengeQuestionModal from './AskChallengeQuestionModal';
import type Mithril from 'mithril';

export default class ChallengeQuestionField extends Component {
  challengeToken: string | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.showChallengeModal = this.showChallengeModal.bind(this);
    this.handleChallengeComplete = this.handleChallengeComplete.bind(this);
  }

  showChallengeModal() {
    if (this.challengeToken !== null) {
        return;
    }

    app.modal.show(
      AskChallengeQuestionModal,
      {
        onChallengeComplete: this.handleChallengeComplete,
      },
      true
    );
  }

  handleChallengeComplete(challengeToken: string) {
    // Handle the challenge completion, e.g., update the component state
    this.challengeToken = challengeToken;
    console.log('Challenge completed with token:', challengeToken);
    m.redraw();
  }

  view() {
    const complete = this.challengeToken !== null;

    return (
      <div className="Form-group">
        <Button className="Button Button--block" icon={complete ? 'fas fa-check' : 'fas fa-question'} onclick={this.showChallengeModal}>
          {complete ? 'Challenge complete' : 'Challenge pending'}
        </Button>
      </div>
    );
  }
}

import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Stream from 'flarum/common/utils/Stream';
import app from 'flarum/forum/app';
import type Mithril from 'mithril';
import ChallengeQuestion from 'src/common/models/ChallengeQuestion';

interface IAskChallengeQuestionModalAttrs extends IInternalModalAttrs {
  onChallengeComplete: (challengeToken: string) => void;
}

export default class AskChallengeQuestionModal extends Modal<IAskChallengeQuestionModalAttrs> {
  challenge: ChallengeQuestion | null = null;
  answer: Stream<string> = Stream('');

  oninit(vnode: Mithril.Vnode<IAskChallengeQuestionModalAttrs, this>) {
    super.oninit(vnode);

    this.loadChallenge();
  }

  className() {
    return 'Modal--medium AskChallengeQuestionModal';
  }

  title() {
    return app.translator.trans('fof-anti-spam.forum.signup.challenge_modal.title');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="AskChallengeQuestionModal--intro">
          <p className="helpText">{app.translator.trans('fof-anti-spam.forum.signup.challenge_modal.intro')}</p>
        </div>
        <div className="AskChallengeQuestionModal--content">
          {this.loading ? (
            <LoadingIndicator />
          ) : (
            <div>
              <div className="AskChallengeQuestionModal--challenge">
                <label>{this.challenge?.question()}</label>
                <input className="FormControl" type="text" bidi={this.answer} />
              </div>
              <div className="AskChallengeQuestionModal--submit">
                <Button className="Button Button--primary" onclick={this.submitChallengeResponse.bind(this)}>
                  Submit
                </Button>
              </div>
            </div>
          )}
        </div>
      </div>
    );
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();

    this.submitChallengeResponse();
  }

  async loadChallenge() {
    this.loading = true;

    const response = await app.store.find<ChallengeQuestion>('challenge');

    this.challenge = response;

    this.loading = false;
    m.redraw();
  }

  async submitChallengeResponse() {
    const challengeToken = await app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/challenge',
      body: {
        data: {
          attributes: {
            challengeId: this.challenge?.id(),
            answer: this.answer(),
          },
        },
      },
    });

    if (challengeToken) {
      this.hide();

      this.attrs.onChallengeComplete(challengeToken);
    }
  }
}

import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import ChallengeQuestion from '../../common/models/ChallengeQuestion';
import Switch from 'flarum/common/components/Switch';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';

interface CreateEditQuestionModalAttrs extends IInternalModalAttrs {
  question: ChallengeQuestion | undefined;
  onSave: () => void;
}

export default class CreateEditQuestionModal extends Modal<CreateEditQuestionModalAttrs> {
  challengeQuestion!: ChallengeQuestion;
  question!: Stream<string>;
  answer!: Stream<string>;
  caseSensitive!: Stream<boolean>;
  isActive!: Stream<boolean>;
  onSaveCallback!: () => void;

  oninit(vnode: Mithril.Vnode<CreateEditQuestionModalAttrs, this>) {
    super.oninit(vnode);

    this.challengeQuestion = this.attrs.question || app.store.createRecord<ChallengeQuestion>('challenge-questions');

    this.question = Stream(this.challengeQuestion.question() || '');
    this.answer = Stream(this.challengeQuestion.answer() || '');
    this.caseSensitive = Stream(this.challengeQuestion.caseSensitive() || false);
    this.isActive = Stream(this.challengeQuestion.isActive() || false);

    this.onSaveCallback = this.attrs.onSave;
  }

  className() {
    return 'FoFAntiSpamCreateEditQuestionModal Modal--medium';
  }

  title() {
    return this.attrs.question
      ? app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.title.edit')
      : app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.title.create');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.question_label')}</label>
            <input
              className="FormControl"
              placeholder={app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.question_placeholder')}
              value={this.question()}
              oninput={(e: Event) => this.question((e.target as HTMLInputElement).value)}
              minLength={10}
              maxLength={255}
            />
          </div>
          <div className="Form-group">
            <label>{app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.answer_label')}</label>
            <input
              className="FormControl"
              placeholder={app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.answer_placeholder')}
              value={this.answer()}
              oninput={(e: Event) => this.answer((e.target as HTMLInputElement).value)}
              maxLength={255}
            />
          </div>
          <div className="Form-group">
            <Switch state={this.caseSensitive()} onchange={this.caseSensitive}>
              {app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.case_sensitive_label')}
            </Switch>
            <p className="helpText">{app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.case_sensitive_help')}</p>
          </div>
          <div className="Form-group">
            <Switch state={this.isActive()} onchange={this.isActive}>
              {app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.is_active_label')}
            </Switch>
            <p className="helpText">{app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.is_active_help')}</p>
          </div>
          <div className="Form-group">
            <Button type="submit" className="Button Button--primary">
              {app.translator.trans('fof-anti-spam.admin.challenge_questions.modal.save_button')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();

    let attributes = {
      question: this.question(),
      answer: this.answer(),
      caseSensitive: this.caseSensitive(),
      isActive: this.isActive(),
    };

    this.challengeQuestion.save(attributes).then(() => {
      this.hide();
      this.onSaveCallback();
    });
  }
}

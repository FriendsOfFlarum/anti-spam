import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import ChallengeQuestion from '../../common/models/ChallengeQuestion';
import ItemList from 'flarum/common/utils/ItemList';
import Button from 'flarum/common/components/Button';
import AntiSpamSettingsPage from './AntiSpamSettingsPage';
import CreateEditQuestionModal from './CreateEditQuestionModal';
import fullTime from 'flarum/common/helpers/fullTime';
import LabelValue from 'flarum/common/components/LabelValue';
import icon from 'flarum/common/helpers/icon';

interface CustomAttrs extends ComponentAttrs {}

export default class ChallengeQuestionsPage extends Component<CustomAttrs> {
  challengeLoading: boolean = false;
  challengeQuestions: ChallengeQuestion[] | null | undefined = null;

  currentPage: number = 1;
  totalPages: number = 1;

  oninit(vnode: Mithril.Vnode<CustomAttrs, this>) {
    super.oninit(vnode);

    this.loadData();
  }

  view(): Mithril.Children {
    return (
      <div className="FoFAntiSpamTabPage FoFAntiSpamSettings--challengeQuestions">
        <div className="Form">
          <h3>{app.translator.trans('fof-anti-spam.admin.challenge_questions.title')}</h3>
          <p className="helpText">{app.translator.trans('fof-anti-spam.admin.challenge_questions.help')}</p>
          {this.challengeLoading && <LoadingIndicator />}
          <div className="FoFAntiSpamSettings--challengeQuestions--actions">{this.controlItems().toArray()}</div>
          {!this.challengeLoading && this.challengeQuestions && this.challengeQuestions.length === 0 && (
            <div>
              <p>{app.translator.trans('fof-anti-spam.admin.challenge_questions.no-records')}</p>
            </div>
          )}
          {!this.challengeLoading && this.challengeQuestions && this.challengeQuestions.length > 0 && (
            <div>
              <div className="ChallengeQuestions--list">
                {this.challengeQuestions.map((challengeQuestion) => {
                  return (
                    <div className="Section ChallengeQuestions--item">
                      <div className="ChallengeQuestions-item--details">{this.detailItems(challengeQuestion).toArray()}</div>
                      <div className="ChallengeQuestions-item--actions">{this.actionItems(challengeQuestion).toArray()}</div>
                    </div>
                  );
                })}
              </div>
              {this.renderPagination()}
            </div>
          )}
        </div>
      </div>
    );
  }

  controlItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'add',
      <Button
        className="Button"
        icon="fas fa-plus"
        enabled={!this.challengeLoading}
        onclick={() => app.modal.show(CreateEditQuestionModal, { onSave: this.loadData.bind(this) })}
        aria-label="add question"
      >
        {app.translator.trans('fof-anti-spam.admin.challenge_questions.add')}
      </Button>
    );

    return items;
  }

  detailItems(challengeQuestion: ChallengeQuestion): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'question',
      <LabelValue label={app.translator.trans('fof-anti-spam.admin.challenge_questions.details.question')} value={challengeQuestion.question()} />
    );

    items.add(
      'answer',
      <LabelValue label={app.translator.trans('fof-anti-spam.admin.challenge_questions.details.answer')} value={challengeQuestion.answer()} />
    );

    items.add(
      'caseSensitive',
      <LabelValue
        label={app.translator.trans('fof-anti-spam.admin.challenge_questions.details.case_sensitive')}
        value={challengeQuestion.caseSensitive() ? icon('fas fa-check') : icon('fas fa-times')}
      />
    );

    items.add(
      'isActive',
      <LabelValue
        label={app.translator.trans('fof-anti-spam.admin.challenge_questions.details.is_active')}
        value={challengeQuestion.isActive() ? icon('fas fa-check') : icon('fas fa-times')}
      />
    );

    items.add(
      'createdAt',
      <LabelValue
        label={app.translator.trans('fof-anti-spam.admin.challenge_questions.details.created_at')}
        value={fullTime(challengeQuestion.createdAt())}
      />
    );

    if (challengeQuestion.updatedAt() !== undefined || challengeQuestion.updatedAt() !== null) {
      items.add(
        'updatedAt',
        <LabelValue
          label={app.translator.trans('fof-anti-spam.admin.challenge_questions.details.updated_at')}
          value={fullTime(challengeQuestion.updatedAt())}
        />
      );
    }

    return items;
  }

  actionItems(challengeQuestion: ChallengeQuestion): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'edit',
      <Button
        className="Button Button--icon"
        icon="fas fa-edit"
        onclick={() => app.modal.show(CreateEditQuestionModal, { question: challengeQuestion, onSave: this.loadData.bind(this) })}
        aria-label="edit question"
      />
    );

    items.add(
      'delete',
      <Button
        className="Button Button--icon Button--danger"
        icon="fas fa-trash"
        onclick={() => {
          this.deleteQuestion(challengeQuestion);
        }}
        aria-label="delete question"
      />
    );

    return items;
  }

  renderPagination(): Mithril.Children {
    return (
      <nav className="ChallengeQuestions--pagination">
        <Button className="Button" disabled={this.currentPage <= 1} onclick={() => this.loadData(this.currentPage - 1)}>
          Previous
        </Button>
        <span>
          Page {this.currentPage} of {this.totalPages}
        </span>
        <Button className="Button" disabled={this.currentPage >= this.totalPages} onclick={() => this.loadData(this.currentPage + 1)}>
          Next
        </Button>
      </nav>
    );
  }

  deleteQuestion(challengeQuestion: ChallengeQuestion) {
    let result = confirm('are you sure?');

    if (!result) {
      return;
    }

    challengeQuestion.delete();
    this.challengeQuestions = this.challengeQuestions?.filter((b) => b.id() !== challengeQuestion.id());
    m.redraw();
  }

  async loadData(page: number = 1) {
    this.challengeLoading = true;
    m.redraw();

    try {
      const response = await app.store.find<ChallengeQuestion[]>('challenge-questions', {
        page: {
          offset: (page - 1) * AntiSpamSettingsPage.ITEMS_PER_PAGE,
          limit: AntiSpamSettingsPage.ITEMS_PER_PAGE,
        },
      });

      this.challengeQuestions = response;
      this.totalPages = response.payload.links?.totalPages || 1;
    } catch (error) {
      console.error(error);
      this.challengeQuestions = [];
    }

    this.challengeLoading = false;
    this.currentPage = page;
    m.redraw();
  }
}

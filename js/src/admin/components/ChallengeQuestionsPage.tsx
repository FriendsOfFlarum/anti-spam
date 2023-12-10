import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import ChallengeQuestion from '../../common/models/ChallengeQuestion';
import ItemList from 'flarum/common/utils/ItemList';
import Button from 'flarum/common/components/Button';
import AntiSpamSettingsPage from './AntiSpamSettingsPage';

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
                    <div className="ChallengeQuestions--item">
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
      <Button className="Button" icon="fas fa-plus" enabled={!this.challengeLoading}>
        {app.translator.trans('fof-anti-spam.admin.challenge_questions.add')}
      </Button>
    );

    return items;
  }

  detailItems(challengeQuestion: ChallengeQuestion): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    return items;
  }

  actionItems(challengeQuestion: ChallengeQuestion): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    return items;
  }

  renderPagination(): Mithril.Children {
    return (
      <nav className="BlockedRegistrations--pagination">
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

  async loadData(page: number = 1) {
    this.challengeLoading = true;
    m.redraw();

    try {
      const response = await app.store.find<ChallengeQuestion[]>('fof/antispam/question', {
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

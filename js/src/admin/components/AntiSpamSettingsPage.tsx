import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import type Mithril from 'mithril';
import ItemList from 'flarum/common/utils/ItemList';
import BlockedRegistrationsPage from './BlockedRegistrationsPage';
import ChallengeQuestionsPage from './ChallengeQuestionsPage';

export default class AntiSpamSettingsPage extends ExtensionPage {
  public static readonly ITEMS_PER_PAGE: number = 20;

  page!: string;

  oninit(vnode: any) {
    super.oninit(vnode);

    this.page = 'settings';
  }

  content() {
    return (
      <div className="FoFAntiSpamSettings">
        <div className="container">
          {this.menuButtons()}
          {this.page === 'settings' && this.settingsContent()}
          {this.page === 'blocked-registrations' && this.blockedRegistrationsContent()}
          {this.page === 'challenge-questions' && this.challengeQuestionsContent()}
        </div>
      </div>
    );
  }

  menuButtons(): Mithril.Children {
    return <div className="MenuButtons">{this.menuButtonItems().toArray()}</div>;
  }

  menuButtonItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'settings',
      <Button className={`Button ${this.page === 'settings' ? 'active' : ''}`} icon="fas fa-cog" onclick={() => this.setPage('settings')}>
        {app.translator.trans('fof-anti-spam.admin.settings.button')}
      </Button>
    );

    items.add(
      'blocked-registrations',
      <Button
        className={`Button ${this.page === 'blocked-registrations' ? 'active' : ''}`}
        icon="fas fa-ban"
        onclick={() => this.setPage('blocked-registrations')}
      >
        {app.translator.trans('fof-anti-spam.admin.blocked_registrations.button')}
      </Button>
    );

    items.add(
      'challenge-questions',
      <Button
        className={`Button ${this.page === 'challenge-questions' ? 'active' : ''}`}
        icon="fas fa-question-circle"
        onclick={() => this.setPage('challenge-questions')}
      >
        {app.translator.trans('fof-anti-spam.admin.challenge_questions.button')}
      </Button>
    );

    return items;
  }

  setPage(page: string): void {
    this.page = page;

    m.redraw();
  }

  settingsContent(): Mithril.Children {
    const apiRegions = ['closest', 'europe', 'us'];
    const tagsEnabled = app.initializers.has('flarum-tags');

    return (
      <div className="FoFAntiSpamTabPage FoFAntiSpamSettings--settings">
        <div className="Form">
          <h3>{app.translator.trans('fof-anti-spam.admin.settings.default-actions.heading')}</h3>
          <p className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.default-actions.introduction')}</p>
          <div className="Section Section--defaultActions">
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.actions.deleteUser',
              label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_user_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_user_help'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.actions.deletePosts',
              label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_posts_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_posts_help'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.actions.deleteDiscussions',
              label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_discussions_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.delete_discussions_help'),
            })}
            {tagsEnabled &&
              this.buildSettingComponent({
                type: 'flarum-tags.select-tags',
                setting: 'fof-anti-spam.actions.moveDiscussionsToTags',
                label: app.translator.trans('fof-anti-spam.admin.settings.default-actions.move_discussions_to_tags_label'),
                help: app.translator.trans('fof-anti-spam.admin.settings.default-actions.move_discussions_to_tags_help'),
                options: {
                  requireParentTag: true,
                  limits: {
                    max: {
                      primary: 1,
                    },
                    min: {
                      primary: 1,
                    },
                  },
                },
              })}
          </div>
          <h3>{app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.heading')}</h3>
          <div className="Introduction">
            <p className="helpText">
              {app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.introduction', {
                a: <Link href="https://stopforumspam.com" target="_blank" external={true} />,
              })}
            </p>
          </div>
          <div className="Section Section--stopforumspam">
            {this.buildSettingComponent({
              type: 'select',
              setting: 'fof-anti-spam.regionalEndpoint',
              options: apiRegions.reduce((o: { [key: string]: string }, p) => {
                o[p] = app.translator.trans(`fof-anti-spam.admin.settings.stopforumspam.region_${p}_label`) as string;
                return o;
              }, {}),
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.regional_endpoint_help'),
              default: 'closest',
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.sfs-lookup',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.sfs_lookup_help'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.report_blocked_registrations',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.report_blocked_registrations_help'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.username',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.username_label'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.ip',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.ip_label'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.email',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_label'),
            })}
            {this.buildSettingComponent({
              type: 'boolean',
              setting: 'fof-anti-spam.emailhash',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_hash_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.email_hash_help'),
            })}
            {this.buildSettingComponent({
              type: 'number',
              setting: 'fof-anti-spam.frequency',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.frequency_help'),
              placeholder: '5',
              required: true,
            })}
            {this.buildSettingComponent({
              type: 'number',
              setting: 'fof-anti-spam.confidence',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.confidence_help'),
              min: 0,
              max: 100,
              placeholder: '50.0',
              required: true,
            })}
            <p className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_text')}</p>
            {this.buildSettingComponent({
              type: 'string',
              setting: 'fof-anti-spam.api_key',
              label: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_label'),
              help: app.translator.trans('fof-anti-spam.admin.settings.stopforumspam.api_key_instructions_text', {
                register: <a href="https://www.stopforumspam.com/forum/register.php" />,
                key: <a href="https://www.stopforumspam.com/keys" />,
              }),
            })}
          </div>
          <hr />
          {this.submitButton()}
        </div>
      </div>
    );
  }

  blockedRegistrationsContent(): Mithril.Children {
    return <BlockedRegistrationsPage />;
  }

  challengeQuestionsContent(): Mithril.Children {
    return <ChallengeQuestionsPage />;
  }
}

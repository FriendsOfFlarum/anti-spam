import Form from 'flarum/common/components/Form';
import FormModal, { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import User from 'flarum/common/models/User';
import app from 'flarum/forum/app';
import type Mithril from 'mithril';
import FormGroup from 'flarum/common/components/FormGroup';
import Stream from 'flarum/common/utils/Stream';
import type { ApiPayloadSingle } from 'flarum/common/Store';

interface HandleSpammerModalAttrs extends IFormModalAttrs {
  user: User;
}

interface AntiSpamData {
  'default-options': {
    deleteUser: boolean;
    deleteDiscussions: boolean;
    deletePosts: boolean;
    spamQuarantine: false | string;
    reportToSfs: boolean;
  };
  stopforumspam?: {
    canReport?: boolean;
    enabled?: boolean;
  };
}

export default class HandleSpammerModal extends FormModal<HandleSpammerModalAttrs> {
  user!: User;
  hardDeleteUser!: Stream<boolean>;
  hardDeleteDiscussions!: Stream<boolean>;
  hardDeletePosts!: Stream<boolean>;
  moveDiscussionsToQuarantine!: Stream<boolean>;
  reportToSfs!: Stream<boolean>;

  oninit(vnode: Mithril.Vnode<HandleSpammerModalAttrs>) {
    super.oninit(vnode);

    this.user = this.attrs.user;

    const antiSpamData = app.forum.attribute<AntiSpamData>('fof-anti-spam');
    const defaultActions = antiSpamData['default-options'];
    this.hardDeleteUser = Stream(defaultActions.deleteUser);
    this.hardDeleteDiscussions = Stream(defaultActions.deleteDiscussions);
    this.hardDeletePosts = Stream(defaultActions.deletePosts);
    this.moveDiscussionsToQuarantine = Stream(!!defaultActions.spamQuarantine);
    this.reportToSfs = Stream(defaultActions.reportToSfs);
  }

  className() {
    return 'HandleSpammerModal Modal--medium';
  }

  title() {
    return app.translator.trans('fof-anti-spam.forum.spammer_modal.title', {
      username: this.user.displayName(),
    });
  }

  content() {
    const tagsEnabled = app.initializers.has('flarum-tags');
    const antiSpamData = app.forum.attribute<AntiSpamData>('fof-anti-spam');
    const sfs = antiSpamData.stopforumspam;
    // Reporting is what needs an API key; looking spammers up never did.
    const sfsEnabled = !!(sfs?.['canReport'] ?? sfs?.['enabled']);

    return (
      <div className="Modal-body">
        <Form description={app.translator.trans('fof-anti-spam.forum.spammer_modal.intro')}>
          <FormGroup
            type="bool"
            stream={this.hardDeleteDiscussions}
            label={app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_discussions_label')}
            help={app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_discussions_help')}
          />
          <FormGroup
            type="bool"
            stream={this.hardDeletePosts}
            label={app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_posts_label')}
            help={app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_posts_help')}
          />
          {tagsEnabled && !this.hardDeleteDiscussions() && (
            <FormGroup
              type="bool"
              stream={this.moveDiscussionsToQuarantine}
              label={app.translator.trans('fof-anti-spam.forum.spammer_modal.move_discussions_tag_label')}
              help={app.translator.trans('fof-anti-spam.forum.spammer_modal.move_discussions_tag_help')}
            />
          )}
          <FormGroup
            type="bool"
            stream={this.hardDeleteUser}
            label={app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_user_label')}
            help={app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_user_help')}
          />
          {sfsEnabled && (
            <FormGroup
              type="bool"
              stream={this.reportToSfs}
              label={app.translator.trans('fof-anti-spam.forum.spammer_modal.report_to_sfs_label')}
              help={app.translator.trans('fof-anti-spam.forum.spammer_modal.report_to_sfs_help')}
            />
          )}
          <div className="Form-group Form-controls">
            <Button className="Button Button--primary" type="submit" loading={this.loading} disabled={this.loading}>
              {app.translator.trans('fof-anti-spam.forum.spammer_modal.process_button')}
            </Button>
          </div>
        </Form>
      </div>
    );
  }

  onsubmit(event: SubmitEvent) {
    event.preventDefault();
    this.loading = true;

    const body = {
      options: {
        deletePosts: this.hardDeletePosts(),
        deleteDiscussions: this.hardDeleteDiscussions(),
        deleteUser: this.hardDeleteUser(),
        moveDiscussionsToQuarantine: this.moveDiscussionsToQuarantine(),
        reportToSfs: this.reportToSfs(),
      },
    };

    app
      .request<ApiPayloadSingle | null>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/users/${this.user.id()}/spamblock`,
        body: body,
      })
      .then((payload) => {
        this.hide();

        if (payload) {
          app.store.pushPayload(payload);
        } else {
          app.store.remove(this.user);
          m.route.set(app.route('index'));
        }

        app.alerts.show(
          { type: 'success' },
          app.translator.trans('fof-anti-spam.forum.spammer_modal.success', { username: this.user.displayName() })
        );
      })
      .finally(() => {
        this.loading = false;
      });
  }
}

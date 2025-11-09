import Form from 'flarum/common/components/Form';
import Button from 'flarum/common/components/Button';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import User from 'flarum/common/models/User';
import app from 'flarum/forum/app';
import type Mithril from 'mithril';
import Switch from 'flarum/common/components/Switch';

interface HandleSpammerModalAttrs extends IInternalModalAttrs {
  user: User;
}

export default class HandleSpammerModal extends Modal<HandleSpammerModalAttrs> {
  user!: User;
  hardDeleteUser!: boolean;
  hardDeleteDiscussions!: boolean;
  hardDeletePosts!: boolean;
  moveDiscussionsToQuarantine!: boolean;
  reportToSfs!: boolean;

  oninit(vnode: Mithril.Vnode<HandleSpammerModalAttrs>) {
    super.oninit(vnode);

    this.user = this.attrs.user;

    const antiSpamData = app.forum.attribute('fof-anti-spam') as Record<string, any>;
    const defaultActions = antiSpamData['default-options'] as Record<string, any>;
    this.hardDeleteUser = defaultActions['deleteUser'];
    this.hardDeleteDiscussions = defaultActions['deleteDiscussions'];
    this.hardDeletePosts = defaultActions['deletePosts'];
    this.moveDiscussionsToQuarantine = defaultActions['spamQuarantine'];
    this.reportToSfs = defaultActions['reportToSfs'];
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
    const antiSpamData = app.forum.attribute('fof-anti-spam') as Record<string, any>;
    const sfsEnabled = !!(antiSpamData['stopforumspam'] as Record<string, any>)?.['enabled'];

    return (
      <div className="Modal-body">
        <Form>
          <p className="helpText">{app.translator.trans('fof-anti-spam.forum.spammer_modal.intro')}</p>
          <div className="Form-group">
            <Switch
              state={this.hardDeleteDiscussions}
              onchange={(value: boolean) => {
                this.hardDeleteDiscussions = value;
                m.redraw();
              }}
            >
              {app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_discussions_label')}
            </Switch>
            <p className="helpText">{app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_discussions_help')}</p>
          </div>
          <div className="Form-group">
            <Switch
              state={this.hardDeletePosts}
              onchange={(value: boolean) => {
                this.hardDeletePosts = value;
              }}
            >
              {app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_posts_label')}
            </Switch>
            <p className="helpText">{app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_posts_help')}</p>
          </div>
          {tagsEnabled && !this.hardDeleteDiscussions && (
            <div className="Form-group">
              <Switch
                state={this.moveDiscussionsToQuarantine}
                onchange={(value: boolean) => {
                  this.moveDiscussionsToQuarantine = value;
                }}
              >
                {app.translator.trans('fof-anti-spam.forum.spammer_modal.move_discussions_tag_label')}
              </Switch>
              <p className="helpText">{app.translator.trans('fof-anti-spam.forum.spammer_modal.move_discussions_tag_help')}</p>
            </div>
          )}
          <div className="Form-group">
            <Switch
              state={this.hardDeleteUser}
              onchange={(value: boolean) => {
                this.hardDeleteUser = value;
              }}
            >
              {app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_user_label')}
            </Switch>
            <p className="helpText">{app.translator.trans('fof-anti-spam.forum.spammer_modal.hard_delete_user_help')}</p>
          </div>
          {sfsEnabled && (
            <div className="Form-group">
              <Switch
                state={this.reportToSfs}
                onchange={(value: boolean) => {
                  this.reportToSfs = value;
                }}
              >
                {app.translator.trans('fof-anti-spam.forum.spammer_modal.report_to_sfs_label')}
              </Switch>
              <p className="helpText">{app.translator.trans('fof-anti-spam.forum.spammer_modal.report_to_sfs_help')}</p>
            </div>
          )}
          <div className="Form-group">
            <Button className="Button Button--primary" onclick={() => this.submitData()} loading={this.loading} disabled={this.loading}>
              {app.translator.trans('fof-anti-spam.forum.spammer_modal.process_button')}
            </Button>
          </div>
        </Form>
      </div>
    );
  }

  submitData() {
    this.loading = true;

    const body = {
      options: {
        deletePosts: this.hardDeletePosts,
        deleteDiscussions: this.hardDeleteDiscussions,
        deleteUser: this.hardDeleteUser,
        moveDiscussionsToQuarantine: this.moveDiscussionsToQuarantine,
        reportToSfs: this.reportToSfs,
      },
    };

    app
      .request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/users/${this.user.id()}/spamblock`,
        body: body,
      })
      .then(() => {
        this.loading = false;
        this.hide();
      });
  }
}

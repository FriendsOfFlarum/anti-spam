import app from 'flarum/admin/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Avatar from 'flarum/common/components/Avatar';
import Button from 'flarum/common/components/Button';
import UserSelectionModal from 'flarum/common/components/UserSelectionModal';
import type User from 'flarum/common/models/User';
import type Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';

/**
 * The account automatic spam flags are raised by, as the backend resolves it.
 */
export interface FlagAccountPayload {
  id: string | null;
  displayName: string | null;
  username: string | null;
  avatarUrl: string | null;
  /** No account chosen, or the chosen one is gone: the first administrator raises flags. */
  isDefault: boolean;
  configuredMissing: boolean;
  chosenInCode: boolean;
}

export interface IFlagAccountSettingAttrs extends ComponentAttrs {
  /** The stored user ID; empty means the default. */
  setting: Stream<string>;
}

/**
 * Chooses the account automatic spam flags are raised by, by name rather than by user ID.
 */
export default class FlagAccountSetting extends Component<IFlagAccountSettingAttrs> {
  protected saved: FlagAccountPayload | null = null;
  protected savedValue = '';

  /** The account shown: the saved one until another is picked. */
  protected user: User | null = null;

  /** The first administrator, when the saved state tells us who that is. */
  protected defaultUser: User | null = null;

  oninit(vnode: Mithril.Vnode<IFlagAccountSettingAttrs, this>) {
    super.oninit(vnode);

    this.saved = app.forum.attribute<FlagAccountPayload | null>('fof-anti-spam.flagAccount') ?? null;
    this.savedValue = this.attrs.setting() || '';

    if (this.saved?.id) {
      app.store.find<User>('users', this.saved.id).then((user) => {
        this.user = user;
        if (this.saved?.isDefault) this.defaultUser = user;
        m.redraw();
      });
    }
  }

  view(): Mithril.Children {
    const value = this.attrs.setting() || '';
    const usingDefault = value === '';
    const shown = usingDefault ? this.defaultUser : this.user;

    return (
      <div className="Form-group FlagAccountSetting">
        <label>{app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_label')}</label>
        <div className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_help')}</div>

        {this.saved?.configuredMissing && value === this.savedValue && (
          <p className="Alert Alert--warning">{app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_missing')}</p>
        )}

        <div className="FlagAccountSetting-current">
          {shown && <Avatar user={shown} />}
          <span className="FlagAccountSetting-name">{shown ? shown.displayName() : null}</span>
          {usingDefault && (
            <span className="helpText FlagAccountSetting-default">
              {app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_default')}
            </span>
          )}
        </div>

        {this.saved?.chosenInCode ? (
          <p className="helpText">{app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_in_code')}</p>
        ) : (
          <div className="FlagAccountSetting-controls">
            <Button className="Button" icon="fas fa-user-check" onclick={() => this.choose()}>
              {app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_choose_button')}
            </Button>
            {!usingDefault && (
              <Button className="Button Button--link Button--text" onclick={() => this.attrs.setting('')}>
                {app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_default_button')}
              </Button>
            )}
          </div>
        )}
      </div>
    );
  }

  protected choose(): void {
    app.modal.show(UserSelectionModal, {
      title: app.translator.trans('fof-anti-spam.admin.settings.content-filter.flag_account_modal_title', {}, true),
      selected: this.user && this.attrs.setting() ? [this.user] : [],
      maxItems: 1,
      onsubmit: (users: User[]) => {
        const [user] = users;

        this.user = user ?? null;
        this.attrs.setting(user ? user.id()! : '');
      },
    });
  }
}

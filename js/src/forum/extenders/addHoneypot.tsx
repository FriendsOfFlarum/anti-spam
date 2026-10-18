import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import type SignUpModal from 'flarum/forum/components/SignUpModal';
import type ItemList from 'flarum/common/utils/ItemList';
import Stream from 'flarum/common/utils/Stream';

// Must match FoF\AntiSpam\Honeypot::FIELD and ::TOKEN.
const FIELD = 'fof_as_comment';
const TOKEN = 'fof_as_token';

// SignUpModal is a lazy-loaded chunk, so it is extended by path: the patch applies when it loads.
// Extending the imported class would patch nothing, as the class is not there yet.
const MODAL = 'flarum/forum/components/SignUpModal';

/**
 * Adds the honeypot to the sign-up form: a field moved off-screen and hidden from assistive
 * technology, which only a bot filling in every input fills, and the signed timestamp the page
 * was served with. The forum only hands out a token while the honeypot is on.
 */
export default function addHoneypot() {
  extend(MODAL, 'oninit', function (this: SignUpModal) {
    this.fofAntiSpamTrap = Stream('');
  });

  extend(MODAL, 'fields', function (this: SignUpModal, items: ItemList<unknown>) {
    if (!app.forum.attribute<string | null>('fof-anti-spam.formToken')) return;

    // Off-screen rather than display:none, which some bots know to skip.
    items.add(
      'fofAntiSpamTrap',
      <div className="FoFAntiSpam-trap" aria-hidden="true" inert>
        <label>
          {app.translator.trans('fof-anti-spam.forum.sign_up.trap_label')}
          <input className="FormControl" type="text" name={FIELD} tabindex={-1} autocomplete="off" bidi={this.fofAntiSpamTrap} />
        </label>
      </div>,
      -100
    );
  });

  extend(MODAL, 'submitData', function (this: SignUpModal, data: Record<string, unknown>) {
    const token = app.forum.attribute<string | null>('fof-anti-spam.formToken');

    if (!token) return;

    Object.assign(data, { [FIELD]: this.fofAntiSpamTrap(), [TOKEN]: token });
  });
}

import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import UserControls from 'flarum/forum/utils/UserControls';
import ItemList from 'flarum/common/utils/ItemList';
import User from 'flarum/common/models/User';
import type Mithril from 'mithril';

export default function addSpamblockControls() {
  extend(UserControls, 'moderationControls', function (items: ItemList<Mithril.Children>, user: User) {
    /** @ts-ignore */
    user.canSpamblock() &&
      items.add(
        'spammer',
        <Button
          icon="fas fa-pastafarianism"
          onclick={() => {
            if (!confirm(app.translator.trans('fof-anti-spam.forum.user_controls.spammer_confirmation') as string)) return;

            app
              .request({
                url: `${app.forum.attribute('apiUrl')}/users/${user.id()}/spamblock`,
                method: 'POST',
              })
              .then(() => window.location.reload());
          }}
        >
          {app.translator.trans('fof-anti-spam.forum.user_controls.spammer_button')}
        </Button>
      );
  });
}

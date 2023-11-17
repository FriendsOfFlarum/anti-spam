import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import UserControls from 'flarum/forum/utils/UserControls';
import ItemList from 'flarum/common/utils/ItemList';
import User from 'flarum/common/models/User';
import type Mithril from 'mithril';
import HandleSpammerModal from '../components/HandleSpammerModal';

export default function addSpamblockControls() {
  extend(UserControls, 'moderationControls', function (items: ItemList<Mithril.Children>, user: User) {
    /** @ts-ignore */
    user.canSpamblock() &&
      items.add(
        'spammer',
        <Button
          icon="fas fa-pastafarianism"
          onclick={() => {
            app.modal.show(HandleSpammerModal, { user });
          }}
        >
          {app.translator.trans('fof-anti-spam.forum.user_controls.spammer_button')}
        </Button>
      );
  });
}

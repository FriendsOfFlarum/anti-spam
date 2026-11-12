import { extend, override } from 'flarum/common/extend';
import Post from 'flarum/forum/components/Post';
import app from 'flarum/forum/app';
import humanTime from 'flarum/common/utils/humanTime';
import Flag from 'ext:flarum/flags/forum/models/Flag';

/**
 * Extend the flagReason function to support 'spam' type flags.
 */
export default function addSpamFlagType() {
  override(Post.prototype, 'flagReason', function (originalReason, flag: Flag) {
    // If the flag is of type 'spam', return our custom display
    if (flag.type() === 'spam') {
      const reason = flag.reason();
      const detail = flag.reasonDetail();

      return [
        app.translator.trans('fof-anti-spam.forum.post.flagged_as_spam_text', { score: reason }),
        !!detail && <span className="Post-flagged-detail">{detail}</span>,
      ];
    }

    // A post held because of its author's email domain, which the flag's reason names.
    if (flag.type() === 'held_email_domain') {
      return app.translator.trans('fof-anti-spam.forum.post.held_email_domain_text', { domain: flag.reason() });
    }

    // Otherwise, return the original result
    return originalReason(flag);
  });
}

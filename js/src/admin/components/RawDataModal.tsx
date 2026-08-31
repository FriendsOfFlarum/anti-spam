import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type BlockedRegistration from '../../common/models/BlockedRegistration';

export interface RawDataModalAttrs extends IInternalModalAttrs {
  registration: BlockedRegistration;
}

/**
 * The stored payloads, verbatim.
 *
 * Loaded on demand: this is reference material for the rare occasion an admin needs to see
 * exactly what was recorded, not something to put in front of them by default. The summary on
 * the page answers the usual question.
 */
export default class RawDataModal extends Modal<RawDataModalAttrs> {
  className() {
    return 'RawDataModal Modal--large';
  }

  title() {
    return app.translator.trans('fof-anti-spam.admin.blocked_registrations.raw_data.title');
  }

  content() {
    const registration = this.attrs.registration;

    const sections: Array<[string, string | null]> = [
      ['sfs-data', registration.sfsData()],
      ['login-provider-data', registration.providerData()],
      ['reasons', registration.reasons()],
    ];

    return (
      <div className="Modal-body">
        {sections.map(([key, value]) => {
          if (!value) return null;

          return (
            <div className="RawDataModal-section">
              <h4>{app.translator.trans(`fof-anti-spam.admin.blocked_registrations.${key}`)}</h4>
              <pre className="RawDataModal-json">{this.format(value)}</pre>
              <Button className="Button Button--text" icon="fas fa-copy" onclick={() => this.copy(value)}>
                {app.translator.trans('fof-anti-spam.admin.blocked_registrations.raw_data.copy')}
              </Button>
            </div>
          );
        })}
      </div>
    );
  }

  /**
   * Pretty-print when it parses; fall back to the stored string so malformed data is still
   * visible here — this view exists precisely for when something looks wrong.
   */
  private format(value: string): string {
    try {
      return JSON.stringify(JSON.parse(value), null, 2);
    } catch {
      return value;
    }
  }

  private copy(value: string): void {
    navigator.clipboard?.writeText(value).then(
      () => app.alerts.show({ type: 'success' }, app.translator.trans('fof-anti-spam.admin.blocked_registrations.raw_data.copied')),
      () => {
        // Clipboard access can be refused (insecure context, denied permission); say so rather
        // than leaving the admin wondering whether the click registered.
        app.alerts.show({ type: 'error' }, app.translator.trans('fof-anti-spam.admin.blocked_registrations.raw_data.copy_failed'));
      }
    );
  }
}

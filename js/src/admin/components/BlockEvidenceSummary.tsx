import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';
import type BlockedRegistration from '../../common/models/BlockedRegistration';
import blockEvidence, { FieldEvidence } from '../utils/blockEvidence';

export interface BlockEvidenceSummaryAttrs extends ComponentAttrs {
  registration: BlockedRegistration;
}

const PREFIX = 'fof-anti-spam.admin.blocked_registrations.evidence';

/**
 * What StopForumSpam reported, per field, at a glance.
 *
 * This shows evidence rather than declaring a single cause. Most blocks trip several rules at
 * once, and a row blocked under one set of thresholds may match none of the current ones, so
 * naming "the" reason would often be wrong. Where the rules that fired were recorded at block
 * time we state them as fact; otherwise we present the signals and let the admin judge.
 */
export default class BlockEvidenceSummary extends Component<BlockEvidenceSummaryAttrs> {
  view(): Mithril.Children {
    const evidence = blockEvidence(this.attrs.registration);

    if (evidence.lookupFailed) {
      return <div className="BlockEvidence BlockEvidence--empty">{app.translator.trans(`${PREFIX}.lookup_failed`)}</div>;
    }

    if (!evidence.fields.length) {
      return <div className="BlockEvidence BlockEvidence--empty">{app.translator.trans(`${PREFIX}.none`)}</div>;
    }

    return (
      <div className="BlockEvidence">
        {this.recordedReasons(evidence.reasons)}
        <ul className="BlockEvidence-fields">{evidence.fields.map((field) => this.field(field))}</ul>
      </div>
    );
  }

  /**
   * The rules that actually fired. Absent on older rows, where we say nothing rather than
   * guessing from thresholds that may since have changed.
   */
  private recordedReasons(reasons: string[] | null): Mithril.Children {
    if (!reasons?.length) return null;

    return (
      <div className="BlockEvidence-reasons">
        <span className="BlockEvidence-reasonsLabel">{app.translator.trans(`${PREFIX}.blocked_by`)}</span>
        {reasons.map((reason) => (
          <span className="BlockEvidence-reason">{app.translator.trans(`${PREFIX}.rule.${reason}`)}</span>
        ))}
      </div>
    );
  }

  private field(field: FieldEvidence): Mithril.Children {
    const badges = this.badges(field);

    return (
      <li className={`BlockEvidence-field BlockEvidence-field--${field.field}`}>
        <span className="BlockEvidence-fieldName">{app.translator.trans(`${PREFIX}.field.${field.field}`)}</span>
        <span className="BlockEvidence-badges">{badges}</span>
      </li>
    );
  }

  private badges(field: FieldEvidence): Mithril.Children {
    const badges: Mithril.Children[] = [];

    if (field.blacklisted) {
      badges.push(<span className="BlockEvidence-badge BlockEvidence-badge--critical">{app.translator.trans(`${PREFIX}.badge.blacklisted`)}</span>);
    }

    if (field.torexit) {
      badges.push(<span className="BlockEvidence-badge BlockEvidence-badge--critical">{app.translator.trans(`${PREFIX}.badge.tor_exit`)}</span>);
    }

    // A field nobody has ever reported is worth stating plainly: it tells the admin which part
    // of the registration was clean, which is as useful as knowing which part was not.
    if (!field.appears && !field.blacklisted) {
      badges.push(<span className="BlockEvidence-badge BlockEvidence-badge--clean">{app.translator.trans(`${PREFIX}.badge.not_listed`)}</span>);

      return badges;
    }

    if (field.confidence !== null) {
      badges.push(
        <span className={`BlockEvidence-badge BlockEvidence-badge--${field.confidence >= 50 ? 'warning' : 'muted'}`}>
          {app.translator.trans(`${PREFIX}.badge.confidence`, { value: field.confidence.toFixed(2) })}
        </span>
      );
    }

    if (field.frequency) {
      badges.push(
        <span className="BlockEvidence-badge BlockEvidence-badge--muted">
          {app.translator.trans(`${PREFIX}.badge.frequency`, { count: field.frequency })}
        </span>
      );
    }

    if (field.lastseen) {
      const seen = new Date(field.lastseen);

      if (!isNaN(seen.getTime())) {
        badges.push(
          <span className="BlockEvidence-badge BlockEvidence-badge--muted">
            {app.translator.trans(`${PREFIX}.badge.last_seen`, { when: humanTime(seen) })}
          </span>
        );
      }
    }

    // Only meaningful on the IP, and only worth the space when SFS actually returned one.
    if (field.asn) {
      badges.push(
        <span className="BlockEvidence-badge BlockEvidence-badge--muted">{app.translator.trans(`${PREFIX}.badge.asn`, { asn: field.asn })}</span>
      );
    }

    // The address the spammer actually controls, once plus-addressing and dot-tricks are undone.
    if (field.normalized && field.normalized !== field.value) {
      badges.push(
        <span className="BlockEvidence-badge BlockEvidence-badge--muted">
          {app.translator.trans(`${PREFIX}.badge.normalized`, { value: field.normalized })}
        </span>
      );
    }

    return badges;
  }
}

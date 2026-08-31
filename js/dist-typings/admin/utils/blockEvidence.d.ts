import type BlockedRegistration from '../../common/models/BlockedRegistration';
/**
 * What StopForumSpam reported about one field of a registration.
 */
export interface FieldEvidence {
    field: 'ip' | 'email' | 'username';
    value: string | null;
    /** The canonical address, when SFS undid plus-addressing or Gmail dot-tricks. */
    normalized: string | null;
    appears: boolean;
    frequency: number | null;
    confidence: number | null;
    lastseen: string | null;
    blacklisted: boolean;
    /** IP only. */
    torexit: boolean;
    asn: number | null;
    country: string | null;
}
export interface BlockEvidence {
    fields: FieldEvidence[];
    /**
     * The rules that actually fired, recorded at block time. Null for rows blocked before we
     * started recording them — deriving it from the response would be guesswork, because the
     * thresholds are settings and may have changed since.
     */
    reasons: string[] | null;
    reasonContext: Record<string, any>;
    /** The lookup itself failed; there is nothing to explain. */
    lookupFailed: boolean;
}
/**
 * Turn the stored StopForumSpam response and recorded reasons into something presentable.
 *
 * Deliberately reports evidence rather than a verdict. Against real data, most blocks trip
 * several rules at once, and a meaningful share match none of the current thresholds at all
 * because those settings changed after the fact — so re-deriving a single "cause" here would
 * be misleading. Where reasons were recorded at block time we show those as fact; otherwise we
 * show what SFS said and let the admin read it.
 */
export default function blockEvidence(registration: BlockedRegistration): BlockEvidence;

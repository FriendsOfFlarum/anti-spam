/// <reference types="mithril" />
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
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
    className(): string;
    title(): string | any[];
    content(): JSX.Element;
    /**
     * Pretty-print when it parses; fall back to the stored string so malformed data is still
     * visible here — this view exists precisely for when something looks wrong.
     */
    private format;
    private copy;
}

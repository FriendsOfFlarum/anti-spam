import Component, { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type BlockedRegistration from '../../common/models/BlockedRegistration';
export interface BlockEvidenceSummaryAttrs extends ComponentAttrs {
    registration: BlockedRegistration;
}
/**
 * What StopForumSpam reported, per field, at a glance.
 *
 * This shows evidence rather than declaring a single cause. Most blocks trip several rules at
 * once, and a row blocked under one set of thresholds may match none of the current ones, so
 * naming "the" reason would often be wrong. Where the rules that fired were recorded at block
 * time we state them as fact; otherwise we present the signals and let the admin judge.
 */
export default class BlockEvidenceSummary extends Component<BlockEvidenceSummaryAttrs> {
    view(): Mithril.Children;
    /**
     * The rules that actually fired. Absent on older rows, where we say nothing rather than
     * guessing from thresholds that may since have changed.
     */
    private recordedReasons;
    private field;
    private badges;
}

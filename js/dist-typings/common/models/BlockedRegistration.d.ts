import Model from 'flarum/common/Model';
export default class BlockedRegistration extends Model {
    ip(): string;
    email(): string;
    username(): string;
    sfsData(): string;
    provider(): string | null;
    providerData(): string | null;
    /**
     * The rules that fired, recorded at block time. Null for rows blocked before this was
     * recorded — the frontend must not invent a reason for those.
     */
    reasons(): string | null;
    attemptedAt(): Date | undefined;
}

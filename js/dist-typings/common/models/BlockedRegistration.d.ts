import Model from 'flarum/common/Model';
export default class BlockedRegistration extends Model {
    ip(): string;
    email(): string;
    username(): string;
    sfsData(): string;
    provider(): string | null;
    providerData(): string | null;
    attemptedAt(): Date | null | undefined;
}

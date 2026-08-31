/**
 * Turn a `key:value` query into the discrete filter params the endpoint expects.
 *
 * The backend dispatches `filter[<key>]` to the registered FilterInterface implementations; it
 * does not parse gambits out of `filter[q]`, which only feeds the fulltext filter. So the
 * recognised tokens have to be split out here, against the keys the backend advertises, and
 * anything left over becomes the free-text search. Negation is `-key:value`.
 *
 * This mirrors AuditBrowser::requestParams() in flarum/audit.
 */
export default function parseBlockedRegistrationQuery(query: string): Record<string, string>;

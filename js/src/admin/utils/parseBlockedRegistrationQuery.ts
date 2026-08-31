import app from 'flarum/admin/app';
import type { BlockedRegistrationFilter } from '../components/BlockedRegistrationSearch';

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
export default function parseBlockedRegistrationQuery(query: string): Record<string, string> {
  const filter: Record<string, string> = {};
  const q = query.trim();

  if (!q) return filter;

  const knownKeys = ((app.forum.attribute('fof-anti-spam.filters') as BlockedRegistrationFilter[] | undefined) || []).map((f) => f.key);

  // Split on whitespace while keeping quoted segments intact, so a value may contain spaces.
  const tokens = q.match(/(?:[^\s"]+|"[^"]*")+/g) || [];
  const leftover: string[] = [];

  for (const token of tokens) {
    const match = token.match(/^(-?)([a-zA-Z_]+):(.*)$/);

    if (match && knownKeys.includes(match[2])) {
      const negate = match[1] === '-';
      const key = (negate ? '-' : '') + match[2];
      const value = match[3].replace(/^"(.*)"$/, '$1');

      // Repeating a key combines the values; the backend filters split on commas.
      filter[key] = filter[key] ? `${filter[key]},${value}` : value;
    } else {
      leftover.push(token);
    }
  }

  if (leftover.length) {
    filter.q = leftover.join(' ');
  }

  return filter;
}

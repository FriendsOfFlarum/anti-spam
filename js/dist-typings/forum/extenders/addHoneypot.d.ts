/**
 * Adds the honeypot to the sign-up form: a field moved off-screen and hidden from assistive
 * technology, which only a bot filling in every input fills, and the signed timestamp the page
 * was served with. The forum only hands out a token while the honeypot is on.
 */
export default function addHoneypot(): void;

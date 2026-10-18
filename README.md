# FoF Anti Spam

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/anti-spam.svg)](https://packagist.org/packages/fof/anti-spam) [![Total Downloads](https://img.shields.io/packagist/dt/fof/anti-spam.svg)](https://packagist.org/packages/fof/anti-spam)

A [Flarum](http://flarum.org) extension. Effective tools to manage spammers on your community

## Features

### Content Filtering

Automatically detect and hold spam content from new users before it becomes visible:

- **Automatic Detection**: Monitors posts and discussion titles (including renames) from recently registered users for common spam indicators
  - Phone numbers in international format
  - Email addresses
  - URLs not on your allowlist, whether or not they carry a scheme
  - Custom blocked words and phrases
  - Advanced regex pattern matching

- **Smart User Targeting**: By default, only monitors users within their first few posts or hours
  after registration. That window is one-way — an account that clears it is never examined again —
  so **Monitor all users** can be turned on to check every post instead. Useful against compromised
  accounts and accounts that wait the window out before starting. Edits are judged the same way: an
  account that has left the window can edit links into its earlier posts unchecked, which only
  **Monitor all users** prevents. Administrators, and anyone whose
  group can hide discussions, are exempt either way

- **Flexible Actions**:
  - Automatically flag suspicious content for moderator review
  - Send suspicious content to the approval queue
  - Separate thresholds for flagging and for hiding, so borderline content can be reviewed without disappearing

- **Custom Flag Type**: Uses a dedicated `spam` flag type with prominent score display to distinguish automatic detections from user reports

- **Configurable via Admin UI or Code**: Full configuration through the admin panel, or use `extend.php` for advanced customization

### Profile Fields

The same detectors run over the parts of a profile a spammer can write into. A profile field has no
approval queue to sit in, so the save is refused rather than hidden — the value is never stored, and
never has a window in which it is publicly visible.

- **Username** — always checked
- **Nickname** — when `flarum/nicknames` is enabled. A nickname is the display name that follows the
  user onto every post they make
- **Bio** — when `fof/user-bio` is enabled
- **Custom profile fields** — when `fof/masquerade` is enabled. A website field is expected to hold a
  link and an email field an address, so the URL or email check is skipped for those; the other
  detectors still apply. A masquerade answer is judged by its owner, so the staff exemption below
  does not cover a moderator editing someone else's answers

Only fields a request actually changes are examined, so a user carrying an older value is not locked
out of editing the rest of their profile. Administrators and anyone who can hide discussions are
exempt, and so are users past the monitoring window unless **Monitor all users** is on.

### User Management

- Set default actions to be processed when a user is marked as a "spammer"
- Select either "delete" or "suspend" for users
- Select "delete", "hide" or "move to tag" for spam discussions
- Select either "delete" or "hide" for spam replies
- Clears the user's bio when `fof/user-bio` is enabled
- Deletes the user's `fof/masquerade` answers
- Removes the user's avatar — often the payload itself, and left behind on every hidden post
  otherwise. The image files are deleted too, including when the account is deleted outright
- Records actions to `flarum/audit` when that extension is enabled

### StopForumSpam Integration

Checks new registrations against the [StopForumSpam database](https://www.stopforumspam.com/) before
the account is created. The check is synchronous — it is not queued — so an account cannot be created
and used in the seconds before a background job would have run.

- **No API key is required to check registrations.** A key is only needed to submit spammers back to
  the database. Without one you still get listings, blacklists, the toxic domain, username and
  network wildcards, Tor exit node detection and confidence scoring
- Covers both registration routes, including OAuth (`fof/oauth`, `fof/passport`), because both reach
  the same API endpoint
- Configurable confidence and frequency thresholds
- Optionally ignore listings older than a given number of days — a sighting from years ago says
  little about who is registering today. Blacklisted domains and networks are always honoured
- Optionally refuse Tor exit nodes
- Optionally refuse whole networks by ASN. StopForumSpam returns the network number on every lookup,
  including for addresses it has never seen, and almost nobody browses a forum from a hosting
  provider
- Regional endpoint selection for compliance
- Blocked attempts are recorded, with the API's verdict, and listed in the admin panel. Each entry
  explains what StopForumSpam reported per field, and which of your rules actually fired, rather
  than leaving you to read raw JSON — the full payloads stay one click away
- The list is searchable with `key:value` filters (`provider:github`, `reason:blacklisted`,
  `attemptedAt:2026-01-01..2026-02-01`), with the available filters shown as clickable examples
- If StopForumSpam cannot be reached the registration is allowed through, and the admin panel says
  so rather than leaving you to guess
- With an API key, blocked attempts can be reported back to StopForumSpam. Only attempts whose email
  or username StopForumSpam already lists are reported, at most once a day per identity — a block
  from Tor, a denied network, or a score against the address alone is never reported, since none of
  those says anything about the person
- OAuth registrations are checked against the email and username the provider supplies, which are
  what the account actually receives

### Dashboard Statistics

A **Spam Defence** widget on the admin dashboard reports how the forum is holding up, each figure
against the previous seven days so a raw total is not left to speak for itself:

- Registrations blocked
- Users marked as spammers — requires `flarum/audit`
- Posts flagged as spam — requires `flarum/audit`
- Spam flags currently awaiting review

Registrations blocked comes from this extension's own table. The two audit-backed counts are read
from the audit log rather than counted live, because that is the only durable record:
`flarum/flags` deletes a flag row when it is dismissed, and again when its post is deleted — which
is what marking the author as a spammer does. A count from that table would fall as moderators
worked through it. The flags table is still read for the one question it can answer honestly: how
many spam flags are open right now.

Both audit-backed figures count from the point `flarum/audit` is installed onward.

The widget is self-contained: it needs no other extension to display, and styles itself rather
than borrowing from one.

### Registration Rate Limiting

Flarum limits how often somebody can post, but not how often they can create accounts. This
extension adds a minimum interval between registrations from the same address, covering both
registration routes. Admins creating accounts by hand are exempt. IPv6 addresses are grouped by their
/64 network, since a single subscriber is routinely given a whole /64.

Bear in mind that offices, schools and mobile networks put many people behind one address. Set the
interval to `0` to switch it off.

### Sign-up Honeypot

A trap for form-filling bots StopForumSpam has not seen yet. Off by default; turn on **Trap
form-filling bots** in the settings.

- The sign-up form gets a field people never see: moved off-screen, hidden from screen readers and
  skipped by the keyboard. A bot filling in every input fills it, and the sign-up is refused
- The page carries a signed timestamp from when it was served. A form sent back faster than a
  person could manage (3 seconds by default, configurable) is refused, as is one carrying a
  timestamp the server never issued. Each page view's token covers a few sign-ups and lasts a day,
  so one fetched page cannot be replayed indefinitely. OAuth sign-ups, which return to a pre-filled
  form, are not held to the minimum time
- Both checks run before the StopForumSpam lookup, so a trapped bot costs no request off-site.
  Nothing is sent anywhere and nothing is asked of real people
- Refused attempts are recorded in the blocked registrations list with the reason (`honeypot`,
  `tooFast`, `formToken`, `noForm`), and are never reported to StopForumSpam: they say something
  about the client, nothing about the identity it typed in
- Sign-ups through the OAuth form are checked the same way. Admins creating accounts are exempt

**Strict mode** also refuses guest sign-ups sent without a form token, as scripts posting straight
to the API do. A script that fetches a page first does get a token, so this raises the bar rather
than closing the door. Leave it off if an app, an SSO bridge or another tool creates accounts through
the API. Someone on a page left open for over a day is asked to reload rather than refused.

If a CDN caches whole pages for guests, every visitor shares the cached page's token and it goes
stale; exclude the forum's pages from such caching before turning strict mode on.

It will not stop bots written for Flarum that render the page and wait, or people paid to sign up;
StopForumSpam and the content filter are there for those.

## Configuration

### Basic Setup (Admin UI)

All settings can be configured through the admin panel:

1. Navigate to Extensions → FoF Anti Spam → Settings
2. Enable content filtering
3. Configure user targeting (monitor everyone, or a post count and account age)
4. Enable detectors (phones, emails, URLs)
5. Set spam score thresholds
6. Configure automatic actions (flag/unapprove)
7. Add allowed domains and blocked words

### Advanced Configuration (extend.php)

For programmatic configuration or version-controlled settings, you can configure the extension in
your forum's `extend.php`:

```php
use FoF\AntiSpam\Extend\ContentFilter;
use FoF\AntiSpam\ContentFilter\Detectors\PhoneDetector;

return [
    // ... your other extenders ...

    // Configure content filtering
    (new ContentFilter())
        // Enable/disable content filtering
        ->enabled(true)

        // User targeting: monitor every user, not just new accounts. Supersedes the two
        // windows below, which can only ever exempt an account permanently once it clears them
        ->monitorAllUsers()

        // User targeting: monitor users within first N posts
        ->monitorUsersUpToPostCount(5)

        // User targeting: monitor users within first N hours
        ->monitorUsersUpToHoursOld(24)

        // Domain allowlist
        ->allowDomain('youtube.com')
        ->allowDomain('github.com')
        ->allowDomains(['stackoverflow.com', 'wikipedia.org'])

        // Custom domain validation
        ->allowDomainCallback(function ($uri, $user) {
            return str_ends_with($uri->getHost(), '.mycompany.com');
        })

        // Block specific patterns (regex)
        ->blockPattern('/\b(viagra|cialis)\b/i', 'Pharmaceutical spam')
        ->blockPattern('/\bcrypto\s*currency\b/i', 'Cryptocurrency spam')

        // Enable/disable detectors
        ->blockPhoneNumbers(true)
        ->blockEmailAddresses(true)
        ->blockUrls(true)

        // Spam score thresholds (0-100)
        // Most matches score 50 points; a bare hostname in a post scores 30
        ->spamScoreThreshold(50)  // At or above this, content is hidden
        ->flagScoreThreshold(30)  // At or above this, content is flagged for review

        // Enable automatic actions
        ->enableAutoUnapprove(true)  // Requires flarum/approval
        ->enableAutoFlag(true)        // Requires flarum/flags

        // Which account raises automatic flags
        ->assignFlagsToModerator(1)  // User ID; without it, the account chosen in the admin panel

        // Disable specific detectors if needed
        ->disableDetector(PhoneDetector::class),
];
```

Configuration set via `extend.php` takes precedence over the same setting in the database. Note that
the admin panel does not currently mark those settings as read-only: the field will still be
editable and will still save, but the value from `extend.php` is the one that applies.

Settings that are not part of the content filter — the StopForumSpam options, the registration
interval, spamblock defaults — are configured through the admin panel only.

## How Content Filtering Works

### Spam Score System

The content filtering system uses a point-based spam scoring mechanism:

- Each detector awards **50 points** per match (capped at 80 points per detector, or 100 for the
  pattern detector). The exception is a bare hostname in a post, such as `suspicious-site.com`
  without a scheme or `www.`: nobody can click it, so it scores **30**
- Multiple detections stack up to create a cumulative score, capped at 100
- Two thresholds, both configurable:
  - **Flag threshold** (default **30**) — at or above this, content is flagged for moderator review
  - **Spam threshold** (default **50**) — at or above this, content is also hidden pending approval

Flagging deliberately starts below hiding, so a weak signal can be put in front of a moderator
without taking the content down.

**Example**: A post containing both a phone number and an email address scores 100 points, which is
flagged and hidden. A single indicator scores 50, which is also both. A single bare hostname in a
post scores 30, so it is flagged for a moderator but stays visible. Anywhere else it counts the full
50: a title is shown on every discussion list, and a profile field can only be refused.

### Custom Flag Type

This extension uses a custom `spam` flag type for automatic detections:

- **Spam score** is stored in the flag's `reason` field
- **Detection details** are stored in the `reason_detail` field
- **Display format**: "Automatic Spam Detection - score {score}"
- **Raised by** the account chosen in the admin panel, picked by name with a search-as-you-type
  selector, or with `assignFlagsToModerator()` in code. Left unchosen, the first administrator
  raises them. If a chosen account no longer exists, the first administrator raises the flag
  instead, the settings page says so, and a warning is logged
- Provides clear visual distinction from user-reported flags

### Detection Methods

**Phone Number Detector**
- Matches international phone numbers with a `+` or `00` prefix and 9 to 15 digits, spaced, dashed,
  dotted or bracketed: `+1234567890`, `00 123 456 7890`, `+44 (0)20 7946 0958`, `+(91) 98765 43210`
- Digits from any script count and spaces of any width, so fullwidth digits, no-break spaces and
  dashes of any kind do not slip through; nor does a number glued to a word (`call+91…`) or
  followed by more digits
- Dates, times, large numbers, sums and the `00` inside numbers such as `1000` are not matched: the
  prefix must start a token, and a country code never begins with 0

**Email Detector**
- Matches email addresses, such as `spam@suspicious-site.com`
- Package pins and git remotes (`flarum/core@2.0.2`, `react@18.3.1`, `git@github.com:org/repo`) are
  not addresses
- Addresses on your allowed domains, or on reserved example domains, do not count

**URL Detector**
- Matches URLs with a scheme (`http://suspicious-site.com`), protocol-relative URLs
  (`//suspicious-site.com`), `www.` prefixed hosts (`www.suspicious-site.com`) and bare hostnames
  (`suspicious-site.com`). Dropping the scheme costs a spammer nothing, so it earns them nothing
- Checks the host against your allowlist; your forum's own domain is always allowed, and so are
  `localhost`, loopback and private network addresses, and the reserved example names
  (`example.com`, `*.test`, `*.example`, `*.invalid`)
- A bare hostname must end in a recognised TLD before it counts, so `README.md`, `extend.php` and
  `main.rs` are not mistaken for links. Country codes that are also English words or common
  identifiers (`it`, `in`, `is`, `to`, `id`, `at`, …) are left out for bare hostnames, so
  `user.id`, `arr.at()` and a sentence missing its space (`that.It`) are not links either. With a
  path they count again, which is how `t.me/…`, `wa.me/…`, `youtu.be/…` and `amzn.to/…` are written,
  and an explicit link on any of them is always caught

**Pattern Detector (Blocked Words)**
- Case-insensitive matching
- Whole word boundary matching (e.g. "viagra" matches "viagra" but not "niagara"). In usernames, `_`
  separates words, so `cheap_viagra_deals` is caught; elsewhere `_` is a word character
- Supports multi-word phrases (e.g. "crypto pump")
- Advanced: Custom regex patterns via the admin panel or `extend.php`

## Deployment note: client IP addresses

Registration checks, the blocked-registration log, the recorded registration address and the
registration interval all identify a visitor by `REMOTE_ADDR`. Forwarding headers such as
`X-Forwarded-For` and `CF-Connecting-IP` are deliberately ignored, because anyone who can reach your
site directly can set them, and honouring them would let a spammer choose the address you check and
rate limit against.

If your forum sits behind Cloudflare, a load balancer or a reverse proxy, configure that layer to
put the real client address into `REMOTE_ADDR` — for example Cloudflare's `mod_remoteip` for Apache,
or nginx's `real_ip` module. Without it every visitor looks to this extension like your proxy.

## Requirements

- Flarum 2.0+
- PHP 8.3+ (inherited from Flarum 2.0)
- `flarum/approval` and `flarum/flags` — both are hard dependencies, installed by Composer

### Optional integrations

Each is used when present and simply not offered when absent:

| Extension | What it adds |
| --- | --- |
| `flarum/audit` | Records spamblocks and automatic spam flags, which the dashboard widget then reports |
| `flarum/suspend` | Suspends a spammer's account rather than leaving it active |
| `flarum/nicknames` | Checks nicknames for spam, as usernames already are |
| `fof/user-bio` | Checks bios for spam, and clears them when a user is marked as a spammer |
| `fof/masquerade` | Checks custom profile answers for spam, and clears them when a user is marked as a spammer |
| `flarum/tags` | Moving a spammer's discussions to a quarantine tag |
| `fof/oauth`, `fof/passport` | OAuth registrations are checked on the same route as ordinary ones |

## More integrations

Future integrations with extensions such as `fof/upload`, and more, are planned soon.

## Installation

Install with composer:

```sh
composer require fof/anti-spam:"*"
```

## Updating

```sh
composer update fof/anti-spam
php flarum migrate
php flarum cache:clear
```

## Links

- [Packagist](https://packagist.org/packages/fof/anti-spam)
- [GitHub](https://github.com/FriendsOfFlarum/anti-spam)
- [Discuss](https://discuss.flarum.org/d/33698)

An extension by [FriendsOfFlarum](https://github.com/FriendsOfFlarum).

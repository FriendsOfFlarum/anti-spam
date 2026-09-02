# FoF Anti Spam

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/anti-spam.svg)](https://packagist.org/packages/fof/anti-spam) [![Total Downloads](https://img.shields.io/packagist/dt/fof/anti-spam.svg)](https://packagist.org/packages/fof/anti-spam)

A [Flarum](http://flarum.org) extension. Effective tools to manage spammers on your community

## Features

### Content Filtering

Automatically detect and hold spam content from new users before it becomes visible:

- **Automatic Detection**: Monitors posts and discussion titles from recently registered users for common spam indicators
  - Phone numbers in international format
  - Email addresses
  - URLs not on your allowlist, whether or not they carry a scheme
  - Custom blocked words and phrases
  - Advanced regex pattern matching

- **Smart User Targeting**: By default, only monitors users within their first few posts or hours
  after registration. That window is one-way — an account that clears it is never examined again —
  so **Monitor all users** can be turned on to check every post instead. Useful against compromised
  accounts and accounts that wait the window out before starting. Administrators, and anyone whose
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

Only fields a request actually changes are examined, so a user carrying an older value is not locked
out of editing the rest of their profile. Administrators and anyone who can hide discussions are
exempt, and so are users past the monitoring window unless **Monitor all users** is on.

### User Management

- Set default actions to be processed when a user is marked as a "spammer"
- Select either "delete" or "suspend" for users
- Select "delete", "hide" or "move to tag" for spam discussions
- Select either "delete" or "hide" for spam replies
- Clears the user's bio when `fof/user-bio` is enabled
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
registration routes. Admins creating accounts by hand are exempt.

Bear in mind that offices, schools and mobile networks put many people behind one address. Set the
interval to `0` to switch it off.

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
        // Each detector awards 50 points per match
        ->spamScoreThreshold(50)  // At or above this, content is hidden
        ->flagScoreThreshold(30)  // At or above this, content is flagged for review

        // Enable automatic actions
        ->enableAutoUnapprove(true)  // Requires flarum/approval
        ->enableAutoFlag(true)        // Requires flarum/flags

        // Which account raises automatic flags
        ->assignFlagsToModerator(1)  // User ID (defaults to 1)

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
  pattern detector)
- Multiple detections stack up to create a cumulative score, capped at 100
- Two thresholds, both configurable:
  - **Flag threshold** (default **30**) — at or above this, content is flagged for moderator review
  - **Spam threshold** (default **50**) — at or above this, content is also hidden pending approval

Flagging deliberately starts below hiding, so a weak signal can be put in front of a moderator
without taking the content down.

**Example**: A post containing both a phone number and an email address scores 100 points, which is
flagged and hidden. A single indicator scores 50, which is also both. A future detector worth 30–40
points on its own would be flagged only.

### Custom Flag Type

This extension uses a custom `spam` flag type for automatic detections:

- **Spam score** is stored in the flag's `reason` field
- **Detection details** are stored in the `reason_detail` field
- **Display format**: "Automatic Spam Detection - score {score}"
- Provides clear visual distinction from user-reported flags

### Detection Methods

**Phone Number Detector**
- Matches international phone numbers with + or 00 prefix
- Requires at least 9 digits
- Examples: `+1234567890`, `00 123 456 7890`

**Email Detector**
- Matches standard email addresses
- Example: `spam@example.com`

**URL Detector**
- Matches URLs with a scheme (`http://suspicious-site.com`), protocol-relative URLs
  (`//suspicious-site.com`), `www.` prefixed hosts (`www.suspicious-site.com`) and bare hostnames
  (`suspicious-site.com`). Dropping the scheme costs a spammer nothing, so it earns them nothing
- Checks the host against your allowlist; your forum's own domain is always allowed
- A bare hostname must end in a recognised TLD before it counts, so `README.md`, `extend.php` and
  `main.rs` are not mistaken for links

**Pattern Detector (Blocked Words)**
- Case-insensitive matching
- Whole word boundary matching (e.g. "viagra" matches "viagra" but not "niagara"). Note that `_` is a
  word character, so `cheap_viagra` will not match either
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

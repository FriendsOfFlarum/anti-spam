# FoF Anti Spam

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/anti-spam.svg)](https://packagist.org/packages/fof/anti-spam) [![Total Downloads](https://img.shields.io/packagist/dt/fof/anti-spam.svg)](https://packagist.org/packages/fof/anti-spam)

A [Flarum](http://flarum.org) extension. Effective tools to manage spammers on your community

## Features

### Content Filtering (New!)

Automatically detect and prevent spam content from new users before it becomes visible:

- **Automatic Detection**: Monitors posts and discussions from recently registered users for common spam indicators
  - Phone numbers in international format
  - Email addresses
  - Suspicious URLs not on your allowlist
  - Custom blocked words and phrases
  - Advanced regex pattern matching

- **Smart User Targeting**: Only monitors users within their first few posts or hours after registration

- **Flexible Actions**:
  - Automatically flag suspicious content for moderator review (requires `flarum/flags`)
  - Send suspicious content to approval queue (requires `flarum/approval`)
  - Configurable spam score thresholds (0-100)

- **Custom Flag Type**: Uses a dedicated `spam` flag type with prominent score display to distinguish automatic detections from user reports

- **Configurable via Admin UI or Code**: Full configuration through admin panel, or use `extend.php` for advanced customization

### User Management

- Set default actions to be processed when a user is marked as a "spammer"
- Select either "delete" or "suspend" for users
- Select "delete", "hide" or "move to tag" for spam discussions
- Select either "delete" or "hide" for spam replies

### StopForumSpam Integration

- Option to submit spammer details to the [StopForumSpam database](https://www.stopforumspam.com/)
- Check new registrations against the [StopForumSpam database](https://www.stopforumspam.com/) to block spammers before they can register on your forum
- Supports OAuth registrations (`fof/oauth`, `fof/passport`)
- Configurable confidence and frequency thresholds
- Regional endpoint selection for compliance

## Configuration

### Basic Setup (Admin UI)

All settings can be configured through the admin panel:

1. Navigate to Extensions → FoF Anti Spam → Settings
2. Enable content filtering
3. Configure user targeting (post count, account age)
4. Enable detectors (phones, emails, URLs)
5. Set spam score thresholds
6. Configure automatic actions (flag/unapprove)
7. Add allowed domains and blocked words

### Advanced Configuration (extend.php)

For programmatic configuration or version-controlled settings, you can configure the extension in your forum's `extend.php`:

```php
use FoF\AntiSpam\Extend\ContentFilter;
use FoF\AntiSpam\ContentFilter\Detectors\PhoneDetector;

return [
    // ... your other extenders ...

    // Configure content filtering
    (new ContentFilter())
        // Enable/disable content filtering
        ->enabled(true)

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
        // Default: 50 for both (single detection triggers actions)
        ->spamScoreThreshold(50)  // Auto-unapprove threshold
        ->flagScoreThreshold(50)   // Auto-flag threshold

        // Enable automatic actions
        ->enableAutoUnapprove(true)  // Requires flarum/approval
        ->enableAutoFlag(true)        // Requires flarum/flags

        // System user for automatic flags
        ->assignFlagsToModerator(1)  // User ID (defaults to 1)

        // Disable specific detectors if needed
        ->disableDetector(PhoneDetector::class),
];
```

Configuration set via `extend.php` will override admin UI settings and be displayed as read-only in the admin panel.

## How Content Filtering Works

### Spam Score System

The content filtering system uses a point-based spam scoring mechanism:

- Each detector awards **50 points** per match (capped at 80-100 points per detector)
- Multiple detections stack up to create a cumulative score
- Default thresholds are set to **50 points** (meaning a single detection triggers actions)

**Example**: A post containing both a phone number and an email address would score 100 points, triggering both flagging and unapproval (if enabled).

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
- Matches HTTP/HTTPS URLs
- Checks against allowlist (your forum domain is auto-allowlisted)
- Example: `http://suspicious-site.com`

**Pattern Detector (Blocked Words)**
- Case-insensitive matching
- Whole word boundary matching (e.g., "viagra" matches "viagra" but not "niagara")
- Supports multi-word phrases (e.g., "crypto pump")
- Advanced: Custom regex patterns via `extend.php`

## Requirements

- Flarum 2.0+
- PHP 8.2+

## More integrations

Future integrations with extensions such as:
- `fof/user-bio`
- `fof/upload`

and more, are planned soon.

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

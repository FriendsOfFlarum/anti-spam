# FoF Anti Spam

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/anti-spam.svg)](https://packagist.org/packages/fof/anti-spam) [![Total Downloads](https://img.shields.io/packagist/dt/fof/anti-spam.svg)](https://packagist.org/packages/fof/anti-spam)

A [Flarum](http://flarum.org) extension. Effective tools to manage spammers on your community

Combines previously seperate extentions (`fof/stopforumspam`, `fof/spamblock`) into one to better fight the war on forum spam.

## Features

- Set default actions to be processed when a user is marked as a "spammer"
- Select either "delete" or "suspend" for users
- Select "delete", "hide" or "move to tag" for spam discussions
- Select either "delete" or "hide" for spam replies
- Option to submit spammer details to the [StopForumSpam database](https://www.stopforumspam.com/)
- Check new registrations agains the [StopForumSpam database](https://www.stopforumspam.com/) to block spammers before they can register on your forum (also supports OAuth registrations)

## More integrations

Future integrations with extensions such as:
- `fof/user-bio`
- `fof/upload`
- `blomstra/spam-prevention`

and more, are planned soon.

## Upgrading from `fof/spamblock` and/or `fof/stopforumspam`

If either of these extensions are installed on your current forum, they will be replaced by this one. Permissions and settings will be carried over.

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
- [GitHub](https://github.com/fof/anti-spam)
- [Discuss](https://discuss.flarum.org/d/PUT_DISCUSS_SLUG_HERE)

An extension by [FriendsOfFlarum](https://github.com/FriendsOfFlarum).

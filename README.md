Feed 2 HipChat
==============

Reads RSS feeds and sends new items as HipChat alerts.

### Installation

1. Clone repository
2. Run `composer install`
3. Copy `config.yml.dist` to `config.yml` and adjust to your needs
4. Create a folder `./cache` and make sure it has write permissions

### Usage

Run `bin/feed2hipchat [FEED_URL] --application=[NAME]` to check an RSS feed for new alerts. The idea is that this command is run periodically via cronjob.


[![Latest Stable Version](https://poser.pugx.org/spartakusmd/ovh-vps-snapshot/v/stable)](https://packagist.org/packages/spartakusmd/ovh-vps-snapshot)
[![Total Downloads](https://poser.pugx.org/spartakusmd/ovh-vps-snapshot/downloads)](https://packagist.org/packages/spartakusmd/ovh-vps-snapshot)
[![Monthly Downloads](https://poser.pugx.org/spartakusmd/ovh-vps-snapshot/d/monthly.png)](https://packagist.org/packages/spartakusmd/ovh-vps-snapshot)

# OVH VPS Automated Snapshot

## Requirements

* [PHP](https://www.php.net/)
* [Composer](https://getcomposer.org/)

## Installation

```
composer create-project spartakusmd/ovh-vps-snapshot
```

## Configuration

### First step

Create credentials by clicking [here](https://api.ovh.com/createToken/index.cgi?POST=/cloud/project/*/instance/*/snapshot&POST=/cloud/project/*/volume/*/snapshot&GET=/cloud/project/*/snapshot&GET=/cloud/project/*/volume/snapshot&DELETE=/cloud/project/*/snapshot/*&DELETE=/cloud/project/*/volume/snapshot/*) !

*Depending on the account zone, the domain may be needed to be customised. Check [Supported APIs](https://github.com/ovh/php-ovh#supported-apis).*

The script requires access to the following API endpoints.

- GET: `/vps/*`
- GET: `/vps/*/snapshot`
- DELETE: `/vps/*/snapshot`
- GET: `/vps/*/tasks/*`
- POST: `/vps/*/createSnapshot`

### Second step

Create `snapshot.yml` in root directory with your credentials and the list of your instances/volumes :

```
---
applicationKey: <ovh_application_key>
applicationSecret: <ovh_application_secret>
consumerKey: <ovh_consumer_key>

apiEndpoint: ovh-eu

vps:
  - "vps123456.ovh.net"
  - "vps452689.ovh.net"

```

## Run

    php snapshot.php

Dry-run mode (simulates the query) :

    php snapshot.php --dry-run

## Crontab

You can automate the snapshot creation by creating a crontab making a call to this tool.

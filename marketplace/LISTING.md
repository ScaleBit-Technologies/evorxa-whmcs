# WHMCS Marketplace listing

Everything needed for the "Add product" form on marketplace.whmcs.com, field by field.
Images are in this folder.

## General Information

**Name**

```
Evorxa Cloud - VPS & VDS Reseller
```

**Category:** Provisioning Modules

**Icon:** `icon.png` (200x200)

**Short Description**

```
Resell Evorxa VPS and VDS servers under your brand: automatic provisioning, a full client control panel with firewall and DDoS view, and one-click plan import.
```

**Long Description**

```markdown
**Sell cloud servers without running your own hypervisors.** Evorxa Cloud connects WHMCS to your Evorxa account. When a client pays, their VPS is created in about a minute, and they manage it from a complete control panel on the service page. Everything is white-label: your clients only ever see your brand.

### Automatic provisioning
* Paid orders create the server automatically, and the client receives a "server ready" email with the IP and login
* Suspend, unsuspend, terminate (prorated refund to your Evorxa wallet), upgrade and renewals are all handled for you
* Safe with your money: a server is never bought twice, and only servers the module created are ever touched

### A control panel your clients will love
* Start, restart, shut down and force off
* SSH/RDP details, root password reveal and reset
* IP addresses with editable reverse DNS
* Usage graphs for CPU, memory, disk and network
* DDoS protection view with attack history and email alerts
* Network firewall with default policies and drag-and-drop rule order
* Reinstall with any operating system or a one-click app (n8n, Coolify, WireGuard, Nextcloud, cPanel, DirectAdmin, Pterodactyl and more)
* Works in any theme, on desktop and mobile, in English and Arabic (RTL)

### Better order form
* One visual "Operating system or app" picker with logos and OS versions, instead of plain dropdowns
* Works with every order form template

### Evorxa Manager (admin)
* Dashboard with setup checklist, wallet balance, next renewal and your monthly margin
* Import Evorxa plans in one click with your markup and price rounding, in every currency, with upgrade paths
* Hourly stock sync, so sold-out plans cannot be ordered
* Server reconciliation and a full activity log with every wallet charge and refund
* Alerts for low wallet, failed builds and servers suspended upstream
* Install / Export: download an upload-ready copy of the module for another WHMCS

### Easy to install
Upload the zip, or run one command over SSH:
`curl -fsSL https://raw.githubusercontent.com/ScaleBit-Technologies/evorxa-whmcs/master/install.sh | bash -s -- /path/to/whmcs`

Then activate the addon, add your Evorxa API token and import plans. The step-by-step guide with screenshots is at https://github.com/ScaleBit-Technologies/evorxa-whmcs

Made by ScaleBit Technologies, the company behind Evorxa.
```

**Payment Type:** Free

**Download URL**

```
https://github.com/ScaleBit-Technologies/evorxa-whmcs/releases/latest/download/evorxa-whmcs.zip
```

This is a direct zip download, not a landing page. It always serves the newest release.

## Current Version

**Version Number:** `1.0.0`

**Release Date:** `09/21/2026`

**Changelog**

```markdown
* First release
* Evorxa Cloud provisioning module: automatic create, suspend, unsuspend, terminate with prorated refund, upgrade and renewal keeper
* Client control panel: power, credentials, reverse DNS editing, usage graphs, DDoS view and alerts, network firewall with drag-and-drop rule order, reinstall with OS or one-click apps
* Order form: visual "Operating system or app" picker with logos
* Evorxa Manager addon: dashboard, one-click plan import with markup, stock sync, server reconciliation, activity log, wallet and build alerts, Install / Export
* English and Arabic (RTL), "Cloud Server Ready" email template, full documentation with screenshots
```

## Compatibility

Tick **WHMCS 8.0 to 8.13** (all 8.x versions). The module uses the WHMCS 8 module APIs and was developed and tested on 8.11.
Leave 9.0 unticked until it has been tested on WHMCS 9. Do not tick 7.x or older.

## Screenshots

| File | Name | Description |
|---|---|---|
| `1-client-control-panel.png` | Client control panel | Power controls, SSH access, IPs with reverse DNS, usage graphs, DDoS view, firewall and reinstall on the service page, in any theme. |
| `2-order-os-or-app.png` | OS or one-click app at checkout | A logo picker on the order form: operating system with version, or apps like n8n, Coolify, cPanel and Pterodactyl. |
| `3-plan-import.png` | One-click plan import | Evorxa Manager shows live Evorxa prices and stock with your price and margin, and creates WHMCS products with your markup. |

## Requirements

Tick "This product has environmental or other requirements" and enter:

```
An Evorxa account (evorxa.com) with wallet balance and an API token. PHP 7.4 - 8.3 with cURL. The WHMCS cron must run every 5 minutes.
```

## GitHub social preview

`github-social-preview.png` (1280x640): upload it in the repository's Settings > General > Social preview. GitHub has no API for this.

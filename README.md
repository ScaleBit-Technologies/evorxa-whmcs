# Evorxa Cloud for WHMCS

Resell [Evorxa](https://evorxa.com) VPS and VDS servers from WHMCS. Orders are provisioned automatically on your Evorxa account, clients get a full server control panel, and a companion addon keeps billing, stock and alerts in line.

- **Server module** `modules/servers/evorxa` - provisioning, lifecycle, client panel, admin tools.
- **Addon** `modules/addons/evorxa_manager` - dashboard, settings, plan importer, server reconciliation, activity log, background sync (it also hosts every hook).

Requirements: WHMCS 8.0+, PHP 7.4-8.3 with cURL, and the WHMCS cron running **every 5 minutes**.

## Install

1. Upload both folders into your WHMCS root (or run `bin/deploy.sh /path/to/whmcs www-data:www-data`).
2. **Setup > Addon Modules**: activate *Evorxa Manager* and give your admin roles access.
3. In the Evorxa dashboard, create an API token (`/api-tokens`). Recommended abilities: `instances:read`, `instances:write`, `analytics:read`, `shield:read`, `shield:write`, `wallet:read`.
4. **Setup > Products/Services > Servers > Add New Server**: module *Evorxa Cloud*, hostname `api.evorxa.com`, paste the token into **Password**, then *Test Connection*.
5. **Addons > Evorxa Manager > Settings**: choose the Evorxa project for client servers (use a dedicated project so they never mix with your own servers) and your hostname suffix (e.g. `example.com`; it also becomes the reverse DNS, so your brand shows instead of Evorxa's).
6. **Plans & Import**: tick plans, set a markup (e.g. 40 %) and rounding, import. Products are created hidden with OS/app choices (shown to clients as a visual picker with logos), a hostname field, stock control and upgrade paths. Review them, then unhide.

The dashboard's setup checklist shows what is still missing.

## How it works

| WHMCS event | Evorxa |
|---|---|
| Order paid (auto-setup) | Server created in your project; the client gets the *Cloud Server Ready* email with IP and password once it has booted (usually 1-5 min) |
| Suspend | Server powered off, client panel locked |
| Unsuspend | Reactivated upstream if needed, deletion cancelled, powered on |
| Terminate | Deleted immediately; Evorxa refunds the unused time to your wallet |
| Upgrade | Plan changed upstream (prorated from the wallet), server rebooted |
| Renewal paid | Renewal keeper runs (see below) |

**Money safety**
- Creating is idempotent: a row is written before the order is sent, a timed-out order is found again by its unique name and never bought twice.
- The module only ever touches servers it created or that you linked, and checks their project before every change.
- Evorxa renews servers from your wallet 5 days before they expire. The keeper only repairs: reactivates a server that was suspended upstream while the client is paid up, cancels a scheduled deletion for an active service, stops the upstream renewal in time when a client cancels at the end of the period, and - if your Evorxa account has auto-renew off - extends a server once per period after the client paid.
- Free and one-time billing cycles are refused (Evorxa would bill forever).
- Low-wallet, failed-build and drift alerts go to admins who receive System emails.

## Client panel

Replaces the service's Overview tab (works with Lagom, Twenty-One and Six):
status, start/restart/shut down/force off, SSH/RDP details with copy, root password reveal/reset, IP addresses with editable reverse DNS, usage graphs, DDoS view with attack history and email alerts, network firewall (rules with drag-and-drop order, default policy, on/off), reinstall with OS or one-click apps, billing summary. English and Arabic (RTL) included.

Each feature can be switched off in *Settings > Client control panel*.

### Customising

- **Templates**: `templates/overview.tpl` and `templates/partials/*.tpl`. All data is in one `{$evx}` variable (documented at the top of `overview.tpl`); templates only use `{$var|escape}`, `{if}` and `{foreach}`.
- **Wording / languages**: copy keys into `lang/overrides/<language>.php` (kept when updating). Add a language by creating `lang/<language>.php`.
- **Colours**: the panel follows your theme's `--brand-primary`; override any `--evx-*` CSS variable.
- **Logos**: OS and app logos live in `templates/assets/logos/{os,apps}/<key>.svg|png`; drop in your own.
- **Email**: edit *Cloud Server Ready* under Setup > Email Templates. Extra merge fields: `{$evx_os}`, `{$evx_location}`, `{$evx_app_name}`, `{$evx_app_url}`, `{$evx_app_username}`, `{$evx_app_password}`, `{$evx_app_note}`, `{$evx_panel_url}`.

## Admin tools

- **Service page > Evorxa tab**: upstream status, paid-until date, scheduled deletion, recent activity, and *Link existing server* (paste a server id, or `unlink`).
- **Module commands**: Boot, Restart, Shut Down, Power Off, Reset Root Password, Sync Now, Reactivate Upstream, Cancel Upstream Deletion.
- **Evorxa Manager**: wallet, renewals, monthly revenue vs cost, needs-attention list, plan importer with margins, server reconciliation (unmanaged or orphaned servers), full activity log with wallet charges and refunds.

## Troubleshooting

- *Addons > Evorxa Manager > Activity Log* shows every action and its result.
- Enable *Utilities > Logs > Module Log* to see raw API calls (the token and passwords are masked).
- A create that timed out shows as *unknown*: the cron resolves it within 15 minutes; do not retry before it is marked failed.
- Evorxa allows 60 API requests per minute per token. The panel caches reads and backs off automatically; use one token per WHMCS install.

## Uninstall

Deactivating the addon stops background sync but keeps all data (tables `mod_evorxa_*`), so linked servers are never orphaned. Terminate or unlink services before removing the files.

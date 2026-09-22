# Changelog

## Unreleased

- Client panel script: also sends the CSRF token as an X-CSRF-TOKEN header, treats non-JSON-API answers (login page, proxy errors) as an expired session, and stops polling once the panel leaves the page.
- Order form picker: fires input + change events, recognises fieldset-wrapped options and removes a stale picker after a re-render (shared with the Paymenter extension).

## 1.0.0 - 2026-09-21

First release, under the MIT license.

- One-command install and update over SSH (`install.sh`), downloads from GitHub releases.
- Documentation: installation guide, usage guide and 27 screenshots (client area, order form, admin, email) in `modules/servers/evorxa/docs/`; included in every package.
- Order form: one "Operating system or app" picker with logos (OS families + versions, app cards); re-attaches when the order form re-draws its options.
- Evorxa Cloud server module: automatic provisioning with idempotent creates, suspend/unsuspend/terminate/upgrade, renewal keeper, project fence, ready email (EN/AR).
- Client panel: power, credentials, IPs with reverse DNS editing, usage graphs, DDoS view and alerts, network firewall with drag-and-drop rule order, reinstall with OS or one-click apps; cached, skeleton-loaded, RTL-ready.
- Evorxa Manager addon: dashboard with setup checklist and monthly economics, settings, plan importer with markup, server reconciliation, activity log, wallet and drift alerts, hourly stock sync, Install / Export (upload-ready package of the installed module).

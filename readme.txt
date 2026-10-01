=== OmniGoCRM ===
Contributors: shivanshukesarwani
Tags: crm, sales, leads, whatsapp, automation, invoices
Requires at least: 6.5
Requires PHP: 8.1
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full-featured WordPress-native CRM based on the OmniGoCRM SaaS product.

== Description ==

OmniGoCRM brings the SaaS CRM workspace to WordPress/shared hosting.

Core modules include Dashboard, Leads, Contacts, Companies, Opportunities, Quotes, Orders, Invoices, Payments, Products, Tasks, Calendar, Automation, Email & SMS conversations, Campaigns, Calls, Reports and Settings.

CRM utilities include WhatsApp templates, media assets, pipeline stages, tags, audit logs, notifications, integrations and public lead capture.

The WordPress edition uses WordPress authentication, PHP REST endpoints, MySQL/MariaDB and WP-Cron. It does not require Node.js, PostgreSQL, Redis, Docker or Kubernetes.

== Installation ==

1. Upload the plugin ZIP in Plugins > Add New > Upload Plugin.
2. Activate OmniGoCRM.
3. Open the OmniGoCRM admin menu.
4. The activation routine creates/updates all CRM database tables.

== WhatsApp ==

WhatsApp is provided through Click-to-Chat. The CRM prepares a message and opens WhatsApp Web, mobile WhatsApp or the desktop protocol. The user completes the Send action in WhatsApp.

== Automation ==

Automation jobs can be queued from the CRM and are processed with WordPress Cron. Built-in JSON actions include notifications and task creation.

== Privacy and security ==

CRM access is controlled using dedicated WordPress capabilities:
omnigocrm_access, omnigocrm_manage, omnigocrm_delete and omnigocrm_settings.

Use HTTPS and standard WordPress hardening practices in production.

== License ==

GPL-2.0-or-later

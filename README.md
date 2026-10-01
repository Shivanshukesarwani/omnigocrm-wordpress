# OmniGoCRM WordPress

WordPress-native edition of **OmniGoCRM**, built to keep the core SaaS CRM feature set available on ordinary WordPress/shared hosting.

## Product scope

This repository is the CMS/WordPress implementation of OmniGoCRM. It runs inside WordPress and uses PHP, WordPress REST, and the existing MySQL/MariaDB database.

### SaaS CRM modules carried into WordPress

- Dashboard and operational KPIs
- Leads with scoring, sources, statuses, search, import/export and conversion
- Contacts
- Companies / Accounts
- Opportunities and sales pipeline
- Multiple pipelines and configurable stages
- Quotes with line items and automatic totals
- Orders with line items and automatic totals
- Invoices
- Payments and payment/revenue reporting
- Products, pricing and tax rate
- Tasks, priorities and due dates
- Calendar view for scheduled tasks
- Notes and related records
- Omnichannel conversations: WhatsApp, SMS, email, calls and web
- Conversation message history
- WhatsApp click-to-chat composer
- Reusable WhatsApp/SMS/email templates
- Media asset links
- Calls and call history records
- Campaign records and scheduling data
- Automations with queued jobs and WordPress Cron processing
- Notifications
- CRM reports
- Lead-source reporting
- Pipeline-by-stage reporting
- Monthly revenue reporting
- CRM audit logs
- CRM tags
- Integrations registry
- Users and WordPress permissions
- Plans and local subscription model
- Public lead-capture REST endpoint
- Responsive admin workspace

## Architecture

```
WordPress
  ├── OmniGoCRM plugin
  │   ├── PHP REST API
  │   ├── SaaS-parity CRM database tables
  │   ├── Responsive admin application
  │   ├── WordPress authentication
  │   └── WP-Cron automation worker
  └── MySQL / MariaDB
```

The independent Node.js/PostgreSQL/Redis SaaS repository remains separate. This WordPress edition maps the same business modules onto WordPress-native storage and authentication.

## Requirements

- WordPress
- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.4+
- WordPress administrator/editor permissions for CRM management

No Node.js, PostgreSQL, Redis, Docker, Kubernetes or VPS is required for this edition.

## Installation

1. Download this repository.
2. Create a ZIP whose root contains `omnigocrm.php`.
3. In WordPress, open **Plugins → Add New → Upload Plugin**.
4. Upload and activate the ZIP.
5. Open **OmniGoCRM** in the WordPress admin menu.
6. The activation routine creates/updates the CRM tables and seeds the default pipeline, stages, plan and WhatsApp templates.

## WhatsApp

OmniGoCRM uses WhatsApp Click-to-Chat links for the shared-hosting edition.

The CRM prepares:

- mobile WhatsApp link
- WhatsApp Web link
- WhatsApp Desktop protocol link

The message is pre-filled and the user completes the final **Send** action in WhatsApp.

A media asset is represented as a public URL inside the prepared message. Native file attachment delivery requires a WhatsApp Business API/provider integration and is intentionally kept separate from the shared-hosting click-to-chat mode.

## Automation

Automations are stored as CRM records with a JSON definition. The plugin queues jobs and uses WP-Cron to process queued jobs.

Supported built-in action examples:

```json
{
  "actions": [
    {
      "type": "notify",
      "title": "Lead follow-up",
      "body": "Follow up with the new lead."
    },
    {
      "type": "create_task",
      "title": "Call lead",
      "priority": "high"
    }
  ]
}
```

The automation engine is intentionally extensible so additional provider actions can be added without changing the CRM record model.

## Public lead capture

A public endpoint is available at:

```
POST /wp-json/omnigocrm/v1/public/leads
```

Example payload:

```json
{
  "first_name": "Amit",
  "last_name": "Sharma",
  "company": "Example Pvt Ltd",
  "email": "amit@example.com",
  "phone": "9876543210",
  "source": "website"
}
```

Use server-side rate limiting, CAPTCHA and spam protection before exposing this endpoint to an untrusted public form.

## Security model

The CRM uses WordPress authentication and nonces for the admin application.

Mutating admin operations require WordPress capabilities. Destructive CRM operations are protected by the same WordPress permission model.

Secrets for third-party integrations should not be stored in ordinary CRM display fields. Use environment/host secrets or a dedicated secret-management/provider integration for production deployments.

## Development

The repository includes GitHub Actions checks for:

- PHP syntax
- JavaScript syntax
- required plugin files

Main runtime files:

- `omnigocrm.php`
- `includes/class-omnigocrm-db.php`
- `includes/class-omnigocrm-rest.php`
- `assets/admin.js`
- `assets/admin.css`

## License

GPL-2.0-or-later.

# OmniGoCRM SaaS → WordPress Feature Parity

This document is the implementation checklist for the WordPress edition. The original repository is the reference for business modules; WordPress replaces the infrastructure layer with WordPress/PHP/MySQL/WP-Cron.

## Core business features

| SaaS capability | WordPress implementation |
|---|---|
| Dashboard | Implemented |
| Leads | Implemented |
| Lead scoring | Implemented as persistent score field + UI |
| Lead source/status/search | Implemented |
| Lead import/export | Implemented |
| Lead conversion | Implemented |
| Contacts | Implemented |
| Companies / Accounts | Implemented; Accounts is exposed through the Companies storage model |
| Opportunities | Implemented |
| Sales pipelines | Implemented |
| Pipeline stages / probabilities | Implemented |
| Tasks | Implemented |
| Calendar | Implemented from scheduled tasks |
| Notes | Implemented |
| Tags | Implemented |
| Conversations | Implemented |
| Messages | Implemented |
| WhatsApp click-to-chat | Implemented |
| WhatsApp templates | Implemented |
| WhatsApp media assets | Implemented as public asset links |
| Calls | Implemented as CRM records |
| Campaigns | Implemented as CRM records + scheduling fields |
| Automations | Implemented with queued jobs + WP-Cron |
| Notifications | Implemented |
| Audit logs | Implemented |
| Reports | Implemented |
| Products | Implemented |
| Quotes | Implemented |
| Quote line items | Implemented |
| Automatic quote totals | Implemented in application logic |
| Orders | Implemented |
| Order line items | Implemented |
| Automatic order totals | Implemented in application logic |
| Invoices | Implemented |
| Payments | Implemented |
| Invoice payment reconciliation | Implemented |
| Plans | Implemented |
| Subscription record | Implemented |
| Integrations registry | Implemented |
| User/role management | Implemented with WordPress roles/capabilities |
| Public lead capture | Implemented |
| Responsive admin workspace | Implemented |

## SaaS capabilities adapted to WordPress

### Authentication
The SaaS edition uses JWT and workspace membership. The WordPress edition uses WordPress authentication, nonces and dedicated CRM capabilities:

- omnigocrm_access
- omnigocrm_manage
- omnigocrm_delete
- omnigocrm_settings

CRM roles:

- Owner
- Admin
- Manager
- Agent
- Viewer

### Workspace model
A WordPress installation is treated as one CRM tenant/workspace. WordPress multisite or multiple separate WordPress installations can provide tenant separation without introducing a second application server.

This is an intentional hosting adaptation: the business features are carried over, while the infrastructure-level multi-workspace JWT model is replaced by WordPress's site/user model.

### Omnichannel providers
The CRM keeps channel records and a unified conversation model. Provider delivery requires the provider itself.

WhatsApp click-to-chat is fully usable without a paid API. Native attachment delivery, inbound webhooks and provider-level delivery/read receipts require a WhatsApp Business/API provider.

Email and SMS records can be stored in the CRM immediately. Actual external delivery requires an integration provider.

### Automation
The WordPress edition runs automations using WP-Cron. JSON action definitions are stored with the automation record, and built-in actions can create notifications and tasks.

The UI is intentionally provider-neutral so integrations can be added later without changing the CRM data model.

## Quality gates

Every feature change should pass:

1. PHP syntax lint
2. JavaScript syntax lint
3. Required plugin structure check
4. GitHub Actions success
5. Live WordPress REST verification before production deployment

## Release packaging

release.yml packages the plugin into a WordPress-installable ZIP and can publish the ZIP to a GitHub Release when a v* tag is created.

## Definition of complete

The WordPress edition is considered feature-complete when every business module in the SaaS reference has:

- persistent WordPress/MySQL storage
- REST API access
- admin UI access
- create/read/update/delete where appropriate
- validation/permissions
- reporting or history where relevant
- no known syntax errors in CI

Provider-dependent delivery remains an integration concern rather than a missing CRM module.

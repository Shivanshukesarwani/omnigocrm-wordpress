# OmniGoCRM WordPress

WordPress-native/CMS edition of OmniGoCRM for shared hosting environments such as Hostinger Premium.

## Goal

This edition runs directly inside WordPress and does **not** require:

- Node.js
- PostgreSQL
- Redis
- Docker
- Kubernetes
- VPS

It uses WordPress PHP, the WordPress REST API, and MySQL/MariaDB through `$wpdb`.

## Features

- CRM dashboard
- Leads
- Contacts
- Companies
- Opportunities
- Tasks
- Products
- Notes
- WhatsApp Click-to-Chat
- WhatsApp message templates
- Media asset links
- Conversations/message history
- Audit logs
- WordPress user authentication and capabilities
- Responsive admin UI

## Installation

1. Download/clone this repository.
2. Zip the repository contents so `omnigocrm.php` is inside the plugin root.
3. In WordPress: **Plugins → Add New → Upload Plugin**.
4. Activate **OmniGoCRM**.
5. Open **OmniGoCRM** in the WordPress admin menu.

The activation hook creates the required CRM tables using WordPress `dbDelta()`.

## Hostinger Premium

The plugin is intentionally designed for shared WordPress hosting. It stores CRM data in the WordPress database rather than requiring a separate PostgreSQL server.

For HTTPS, use the SSL certificate/domain configuration supplied by your hosting provider. The plugin does not attempt to provision server-level certificates.

## WhatsApp Click-to-Chat

The CRM creates a `https://wa.me/<number>?text=...` link. WhatsApp opens with the message prepared; the user presses Send. A media asset is represented as a public URL in the message. Automatic file attachment requires a later WhatsApp Business API integration.

## Architecture

```
WordPress
  └── OmniGoCRM plugin
      ├── PHP REST API
      ├── MySQL/MariaDB tables
      ├── WordPress authentication
      ├── JavaScript admin application
      └── WhatsApp Click-to-Chat
```

The independent Node.js/PostgreSQL/Redis OmniGoCRM repository remains separate and is not modified by this project.

## License

GPL-2.0-or-later.

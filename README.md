# OmnigoCRM

Standalone React + TypeScript CRM application backed by Supabase.

## Architecture

```
Browser / PWA
   ↓
React + TypeScript + Vite + Material UI
   ↓
Supabase Auth
   ↓
PostgreSQL + Row Level Security
   ↓
Supabase Storage / Realtime
```

WordPress is not required by the application.

## Stack

- React 19 + TypeScript
- Vite
- Material UI
- React Router
- Supabase JavaScript client
- Supabase Auth
- PostgreSQL
- Supabase Storage
- Workspace-aware Row Level Security

## Current CRM foundation

- Authentication: sign in and account creation
- Automatic first-workspace initialization
- Workspace selector
- Dashboard KPI queries
- Leads
- Contacts
- Deals
- Tasks
- Files metadata
- Settings foundation
- Supabase-backed session persistence
- Workspace-scoped queries
- Hostinger SPA fallback

## Environment

Create a `.env` file locally:

```env
VITE_SUPABASE_URL=https://wckdhleoxzmcqfzykxvq.supabase.co
VITE_SUPABASE_PUBLISHABLE_KEY=YOUR_SUPABASE_PUBLISHABLE_KEY
```

Use the Supabase **publishable** key for the browser application. Never place a Supabase secret/service-role key in Vite environment variables or client-side code.

## Local development

```bash
npm install
npm run dev
```

Production build:

```bash
npm run build
```

The generated `dist/` directory can be deployed to Hostinger as a static website.

## Hostinger deployment

1. Build the application with `npm run build`.
2. Upload the contents of `dist/` to the domain's `public_html` directory.
3. Keep the generated `.htaccess` file so React Router routes resolve correctly.
4. Configure the production Supabase environment values in the build environment before running the build.

The application does not require PHP, WordPress, Node.js runtime, MySQL or PostgreSQL on Hostinger.

## Supabase

The production Supabase project is:

- Project: `Omnigocrm`
- Region: `ap-northeast-2`
- URL: `https://wckdhleoxzmcqfzykxvq.supabase.co`

The existing CRM schema contains profiles, workspaces, members, leads, contacts, customers, deals, tasks, notes, files, activities and WhatsApp templates. The private `omnigocrm-files` Storage bucket is protected with Storage RLS.

## Roadmap

The React application is being expanded toward the full OmnigoCRM SaaS feature set:

- Full CRUD screens and forms
- Advanced dashboard and reports
- Sales pipelines
- Contacts/customers conversion
- Tasks and calendar
- Notes and activities
- File upload/download through Supabase Storage
- WhatsApp click-to-chat composer and templates
- Inbox/broadcast architecture
- Automation engine
- Calling/dialer architecture
- Team roles and permissions
- SaaS billing/admin
- Notifications and audit logs
- Import/export
- Realtime updates
- PWA/mobile experience
- Production hardening and E2E testing

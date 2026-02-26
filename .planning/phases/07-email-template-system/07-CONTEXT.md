# Phase 7: Email Template System - Context

**Gathered:** 2026-02-26
**Status:** Ready for research

<vision>
## How This Should Work

Admin opens Filament, sees every transactional email the kit sends -- welcome, email verification, password reset, invoice, subscription renewal, credits low, account restricted. Each template has a subject line and body with Mustache placeholders. Admin edits copy and subject lines without deploying. Developers register new email types with their placeholders.

All emails inherit a base layout: header with logo/app name, clean typography, subtle footer. The layout branding (logo URL, primary color, company name, footer address) is config-based -- set once in `.env`, cached with `config:cache`, lives in version control. Not a database setting, not an admin UI. It changes once when a developer starts a project and almost never again.

Templates use Mustache exclusively. No Blade, no PHP execution, no raw HTML escape hatch. The admin edits text with `{{placeholders}}`, and that is the entire surface area they have access to.

</vision>

<essential>
## What Must Be Nailed

- **Security boundary** -- Mustache only, enforced at every level. No Blade rendering path, no `{!! !!}` anywhere near template content, no "raw HTML" mode in the editor. A compromised admin account editing a template must not become a remote code execution vector. This is the only failure mode that is catastrophic and irreversible.
- **Seeded starter templates** -- Every email the kit already sends has a pre-built template with correct placeholders, ready to customize in Filament on day one.

</essential>

<specifics>
## Specific Ideas

- Spatie `laravel-database-mail-templates` package for DB-stored templates with Mustache
- Config-based branding, not database:
  ```php
  // config/mail-branding.php
  'logo_url' => env('MAIL_LOGO_URL', null),
  'primary_color' => env('MAIL_PRIMARY_COLOR', '#4F46E5'),
  'company_name' => env('APP_NAME', 'MyApp'),
  'footer_address' => env('MAIL_FOOTER_ADDRESS', ''),
  'unsubscribe_url' => env('MAIL_UNSUBSCRIBE_URL', null),
  ```
- Filament resource: list templates, edit subject + body, preview rendered output, test-send to admin's email
- Textarea with Mustache syntax for the editor -- no WYSIWYG (HTML email editors are a nightmare)
- Activity log captures template edits (who changed what) -- no template versioning table needed

**What NOT to ship:**
- Per-tenant template overrides (add `tenant_id` column when white-labeling is needed)
- WYSIWYG editor for email body
- Template versioning table

</specifics>

<notes>
## Additional Context

The user sees this as solving one problem: "I want to change the welcome email subject line without deploying." Everything flows from that. Admin-editable copy, developer-defined placeholders, Mustache for safety.

Per-tenant branding was explicitly scoped out as a white-label feature for B2B platforms -- a tiny subset of SaaS that doesn't belong in a starter kit. Developer convenience (clean API for sending templated emails) is already what Spatie's package provides by default -- nothing extra to build.

</notes>

---

*Phase: 07-email-template-system*
*Context gathered: 2026-02-26*

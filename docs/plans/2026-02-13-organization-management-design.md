# Organization Management in Settings

## Overview

Add organization management pages to the settings sidebar so users can create organizations, invite members via email, and manage membership. Owners get full control; members get a read-only view.

## Location

New **"Organization"** group in the settings sidebar between Billing and Privacy. Two pages:

- `/settings/organization` — manage org details + members (or create if none)
- Invite acceptance route — signed URL for joining

## Page Behavior

### Owner view
- Org name (editable inline)
- Member list table: name, email, role, status, joined date
- "Invite Member" form: email input + send button
- Remove member action per row
- Revoke pending invite action
- Org switcher dropdown if user belongs to multiple orgs

### Member view
- Org name (read-only)
- Member list (read-only, no actions)
- "Leave Organization" button
- Org switcher dropdown if user belongs to multiple orgs

### No organization
- Prompt to create one: name input + create button
- Auto-assigns creator as owner

## Invitation Flow

1. Owner enters email, submits form
2. System creates pivot row with `status=invited`, `invited_by`, `invited_at`
3. Sends Laravel Mailable with signed URL (`URL::signedRoute()`)
4. Recipient clicks link:
   - **Existing user**: auto-accepts (pivot status -> active, joined_at set), redirect to dashboard
   - **New user**: redirect to registration with invite token in session, after signup auto-join
5. Owner can revoke pending invites (deletes pivot row)

## Technical Decisions

- **Volt pages** in `resources/themes/anchor/pages/settings/` (matches existing pattern)
- **Laravel Mailable** for invite emails — simple, testable
- **Signed URLs** via `URL::signedRoute()` — no tokens table needed, secure, expirable
- **Invite acceptance controller** registered in `wave/routes/web.php`
- **No new models** — uses existing Organization model + organization_user pivot

## Out of Scope

- Organization creation from main sidebar
- Role management beyond owner/member
- Organization deletion (admin-only via Filament)
- Seat limits enforcement

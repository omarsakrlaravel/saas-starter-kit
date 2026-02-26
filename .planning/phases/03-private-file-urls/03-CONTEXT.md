# Phase 3: Private File URLs - Context

**Gathered:** 2026-02-26
**Status:** Ready for research

<vision>
## How This Should Work

This is not a file feature — it is file infrastructure. The app already produces files (avatars, exports, attachments, invoice PDFs). This phase secures them so no file can leak across tenant boundaries.

One unified pattern covers all file types — user uploads and admin-distributed resources alike. Every file has `tenant_id` + `uploaded_by_user_id`. Every file is private by default (no public disk for user content). Every download goes through an authorization check before generating a signed URL.

The infrastructure is invisible. Users never interact with "files" as a concept — they interact with their avatar, their attachment, their export. Developers building on the kit use a `FileService` with `store()` and `signedUrl()` methods. Existing upload/display points in the kit get rewired to use this service instead of raw Storage calls and asset paths.

No file manager page. No upload UI. No "my files" section. If a product needs those, they build on top of the `files` table and `FileService` that already exist — the security layer is already there.

</vision>

<essential>
## What Must Be Nailed

- **No cross-tenant file access** — A user in org A can never access a file belonging to org B. This is the bug class this phase eliminates. Safety beats ergonomics when forced to choose.
- **Private by default** — Nothing on the public disk except truly public assets (logos, marketing images). All user/tenant content goes through signed URLs.
- **Tenant check before signed URL generation** — The signed URL is only generated after the tenant membership check passes. Authorization first, URL second.

</essential>

<specifics>
## Specific Ideas

- `files` table with `tenant_id` + `uploaded_by_user_id` + `BelongsToTenant` trait
- `FileService` with two methods: `store()` and `signedUrl()`
- Single download route that validates the signed URL + checks tenant membership
- Existing upload points (avatar, attachments) rewired to use `FileService::store()`
- Existing display points rewired to use signed URLs instead of raw asset paths
- Default authorization policy: user must be a member of the tenant that owns the file (covers 90% of cases)
- Policy is overridable — product builders can narrow ("only uploader can download") or widen ("anyone with permission X") by overriding the policy
- `file_shares` (external sharing with tokens, expiry, download limits) is deferred to the product layer — documented as a recommended extension pattern, not shipped

</specifics>

<notes>
## Additional Context

The reasoning follows the same principle as tenant data scoping: safety-first infrastructure that prevents a bug class by default. A locked-down file system with a slightly awkward override pattern is safe and fixable. A clean extensibility pattern that has a tenant leak is dangerous.

Extensibility will be informed by actual usage rather than speculative API design. If the policy override pattern is clunky on v1 (developer has to copy-paste the whole policy instead of extending a method), that is annoying but harmless — clean it up after real products reveal what they actually need to override.

The migration from "no sharing" to "sharing" is additive — one table, one policy change. Zero retrofit cost, which confirms it is safe to defer.

</notes>

---

*Phase: 03-private-file-urls*
*Context gathered: 2026-02-26*

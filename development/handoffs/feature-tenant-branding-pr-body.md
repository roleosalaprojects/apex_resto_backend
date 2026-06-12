## Summary

Tenant branding end-to-end: SuperAdmin-managed color palettes, admin-side branding page, runtime theming across `/admin`, `/shop`, customer portal and transactional emails. Plus a Terms-and-Conditions consent flow for self-registered AND non-self-signup customers, and a few security/UX fixes that surfaced along the way.

- 21 commits, 16 new files, ~64 tests (BrandingService unit + SuperAdmin palette CRUD + Admin branding + API + Customer auth + Item show regression)
- All 56 tests across these surfaces are green
- Pint clean on every commit

## What landed

**Architecture**
- `color_palettes` table (SuperAdmin-managed, soft deletes, default-palette invariant enforced by observer)
- `branding_settings` table (tenant-scoped, 1 row per tenant)
- `BrandingService` with `forCurrentTenant()` (auth-driven) and `forStorefront()` (anonymous visitors get the platform's primary tenant's branding, not the platform default)
- 5-minute per-tenant + per-storefront cache, busted by observers on any palette/setting change
- `BrandingResource` + `GET /api/v1/branding` for apex_pos consumption

**SuperAdmin**
- New `Appearance` sidebar group with `/superadmin/color-palettes`
- DataTables list + paired hex/color-picker create+edit forms with live preview
- Set-default / toggle-active / soft-delete actions
- Default palette cannot be deleted or deactivated

**Tenant admin**
- `/admin/settings/branding` — palette grid (curated, 10 starters), Metronic image-input logo upload (250×130, PNG/JPG/WEBP only, GD re-encode), brand name with charset regex
- Admin layout `<title>`, sidebar logo, `--bs-primary` driven by chosen palette
- Sidebar Branding link added to the Settings group

**Storefront**
- `/shop` layout: `--qb-primary` / `--qb-primary-dark` / `--qb-accent` plus `--qb-*-rgb` companion tokens for `rgba()` shadows
- Hero gradient + category-icon tints + carousel shadows all route through brand tokens
- Customer auth pages (login / register / verify-email) themed and forced to light mode (admin dark toggle no longer leaks)
- Customer portal layout, dashboard, profile, orders themed via `forStorefront()`
- `background-attachment: fixed` so the gradient is flush at any scroll height
- "Why Choose Quick Baskets?" → "Why Choose {brand_name}?"

**Email**
- `vendor/mail/html/header` renders the tenant logo or brand name
- `VerifyEmailNotification` overrides the `From:` name (Gmail no longer shows "Laravel") and templates the subject as `{brand_name} - Verify Your Email Address`

**Terms & Conditions**
- New `customers.terms_accepted_at` timestamp (additive migration)
- `/shop/terms` public page rendering a Philippines Data Privacy Act (RA 10173) flavored policy
- Required acceptance checkbox on `/customer/register`, validated client- and server-side
- `EnsureCustomerHasAcceptedTerms` middleware gates `customer.auth + customer.verified` routes — customers created at POS / admin (where the gate doesn't exist) are bounced to `/shop/terms` on first authenticated visit, accept via a single button click, get redirected back

**Security mitigations (§1 of spec)**
- Strict 6-digit hex regex on every color input + `sanitizeHex()` read-path guard (CSS injection)
- PNG / JPG / WEBP only, validated by extension + MIME + `getimagesize` + GD re-encode (SVG XSS + polyglots)
- Tenant boundary derived from `auth()`, never input
- Server-generated upload path `branding/{tenant}/{random40}.{ext}` (path traversal)
- `X-Content-Type-Options: nosniff` global middleware (MIME sniffing)
- `brand_name` charset regex + Blade `{{ }}` only (stored XSS)

**Other fixes bundled in this branch**
- `/shop` hard-coded to light mode — admin's localStorage dark-mode toggle no longer leaks to anonymous customers
- `.claude/settings.json` ask-rule on destructive Sail/Artisan commands (`migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe`, `db:seed --force`) — harness backstop after the dev DB was wiped twice during the build

## Specs in this PR

- `development/specs/tenant_branding/plan.md` — backend + web plan with locked decisions and §1 security analysis
- `development/specs/tenant_branding/apex_pos_spec.md` — Flutter-side spec for a separate agent to execute against `../apex_pos`
- `development/specs/shop_featured_curation/plan.md` — the user-queued follow-up (locked decisions, scheduled to start in parallel on `feature/shop-featured-curation`)

## Test plan

- [ ] Pick a curated palette via `/admin/settings/branding` → reload `/shop` → confirm hero + buttons + nav use new colors
- [ ] Upload a logo → confirm it renders in admin sidebar, shop nav, customer portal nav, and verification email
- [ ] Set a brand name → confirm it replaces "APEX" / "Quick Baskets" in titles, navbars, email From, and email subject
- [ ] Open `/customer/register` in a fresh browser session → confirm the storefront tenant's branding renders even without an authenticated user
- [ ] Try to register without ticking the Terms box → confirm both client-side and server-side block submission
- [ ] As a POS-created customer (terms_accepted_at NULL), log in → confirm redirect to `/shop/terms` and accept button stamps the timestamp
- [ ] Toggle admin dark mode → reload `/shop` → confirm shop stays light
- [ ] `/superadmin/color-palettes` — try to delete the default → confirm 422
- [ ] Trigger a verification email → confirm From name is the brand, not "Laravel", and subject is branded

## Out of scope (parking lot)

- Dark-mode variants per palette
- Schedule-based featuring (different spec)
- Drag-and-drop curation page for featured items (different spec, locked to start in parallel)
- apex_pos Flutter implementation (handed off to a separate agent via `apex_pos_spec.md`)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

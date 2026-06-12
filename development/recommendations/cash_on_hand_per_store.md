# Cash on Hand Per Store — Plan (deferred)

**Status:** Planning document. Design locked; build deferred.
**Discussed:** 2026-05-13
**Owner decision:** Save and defer. Revisit when ready to build.

---

## Why this exists

Owner's primary driver: **theft prevention from cashiers**, especially as Leteres expands to multiple branches where the owner can't be physically present at every store.

The bot already has read access to most business data, but cannot tell whether "PHP 100,000 was rang up today on cash sales but only PHP 95,000 was deposited" — because there is no system-of-record for cash sitting *outside* the bank, in the till. We need a virtual ledger that:

- Auto-tracks cash flowing through the POS (sales in, cash-outs out)
- Tracks deposits to real banks as transfers FROM the till TO the bank
- Per-store, so the owner can spot which branch's cashier is hoarding or shorting
- Audit-logged via the existing `Auditable` trait

This unlocks signals the bot can surface — per-store mismatch alerts, stale-cash detection, cross-store velocity divergence, per-cashier discrepancy attribution.

## Bigger picture

The codebase doesn't currently track "cash on hand" anywhere. Z-Readings record what the POS rang up; bank_transactions record what hit the real banks. The gap between the two — the cash physically sitting in someone's till or someone's hand at end of shift — has no schema home today. This plan creates that home.

## The architectural choice that drove everything

**Cash on Hand is modeled as a virtual bank account** (`account_type=5 = cash_on_hand`), not as a separate concept with its own tables.

Why this matters:
- Every operation that affects it has an equivalent in the existing bank model (deposit, withdrawal, transfer)
- All existing /banks endpoints, audit logs, balance tracking, alerts, transaction history work for free
- Bot reads it like any other bank via `/openclaw/banks/*` — no new abilities needed
- One enum value + one nullable column is the entire schema impact

The alternative (separate `cash_registers` table) was rejected because it would duplicate ~80% of the bank model with no benefit.

## Schema changes

```sql
-- stores
ALTER TABLE stores ADD COLUMN has_cash_register BOOLEAN NOT NULL DEFAULT TRUE;

-- banks
ALTER TABLE banks ADD COLUMN store_id BIGINT NULL;
ALTER TABLE banks ADD FOREIGN KEY (store_id) REFERENCES stores(id);

-- banks.account_type enum extended:
--   0 = savings, 1 = checking, 2 = credit, 3 = passbook, 4 = ewallet
--   5 = cash_on_hand   (NEW)

-- enforce one Cash on Hand register per store
CREATE UNIQUE INDEX banks_cash_on_hand_unique
ON banks(user_id, store_id, account_type)
WHERE account_type = 5;
```

| Bank row example | account_type | store_id | Meaning |
|---|---|---|---|
| BDO Network Bank | 1 | NULL | Tenant-level real bank |
| GCash Maya | 4 | NULL | Tenant-level e-wallet |
| Cash on Hand — DEFAULT STORE | 5 | 17 | Per-store till |
| Cash on Hand — Branch 2 | 5 | 23 | Per-store till |

Real banks (BDO, BPI, GCash) stay with `store_id=NULL` — they're tenant-wide. Only Cash on Hand banks carry a `store_id`.

## Behavior matrix

| Action | `has_cash_register` state | Effect on Cash on Hand |
|---|---|---|
| Create store | `true` (default) | Auto-create register, named "Cash on Hand — {store_name}" |
| Create store | `false` (admin unchecked it for warehouses/storage) | No register created |
| Toggle existing store false → true | (upgrading warehouse to a branch) | Auto-create register, flash confirmation |
| Toggle existing store true → false | (downgrading) | Existing register stays intact, balance frozen, history preserved; observer simply won't fire for future Z-Readings |
| Rename store | (regardless of toggle) | If register exists, observer renames it to "Cash on Hand — {new_name}" |
| Delete store, register balance = 0 | — | Allow delete; soft-delete both together |
| Delete store, register balance ≠ 0 | — | **Block with 422**: "Cash on Hand — {store_name} has a balance of PHP {amount}. Zero it out first via transfer to another bank or an adjustment, then retry." |
| Try to rename Cash on Hand bank manually | — | Reject server-side; name is auto-managed |
| Try to delete Cash on Hand bank manually | — | Reject server-side; tied to its store |

## Observer hooks

- `StoreCreated` — if `has_cash_register=true`, create register
- `StoreUpdated` — if `has_cash_register` flipped to `true`, create register; if `name` changed AND register exists, rename register
- `StoreDeleted` — require zero-balance precondition; soft-delete linked register
- `ZreadingCreated` — look up register by `(user_id, store_id, account_type=5)`; insert split deposit (cash sales + cash-ins) + withdrawal (cash-outs + cash refunds); idempotent via `reference_number = "ZCASH-IN-{z_id}"` / `"ZCASH-OUT-{z_id}"`; silently skip + log warning if no register found (defensive — orphan Z-Reading for non-branch store)

## Approach choices documented

### Why Z-Reading drives the ledger, not pos_logs (Approach B1, not B2)

- Z-Reading is the official shift-close audit point
- One event per shift keeps the ledger readable
- pos_logs is the raw event source; Z-Reading is the rollup — putting Cash on Hand on both would double-count
- The bot's primary use case is end-of-day audit, not real-time till balance

Tradeoff: Cash on Hand balance is only accurate at end-of-shift, not mid-day. Acceptable.

### Why split into deposit + withdrawal per Z-Reading, not single net

The Z-Reading observer fires TWO bank_transaction rows per Z:
- `ZCASH-IN-{z_id}`: deposit, sum of cash sales + cash-ins
- `ZCASH-OUT-{z_id}`: withdrawal, sum of cash-outs + cash refunds

Reasoning: the bot can show the owner "Friday brought in PHP 100,000 / paid out PHP 8,000 / net PHP 92,000". A single net transaction would hide the gross movement in either direction.

### Why per-store from day one (not tenant-level v1 → per-store v2)

The user's expansion plans are real. Theft prevention requires per-store accountability. Lumping cash across stores defeats the entire purpose. Per-store is the design from version one.

Bonus: per-store works identically for single-store tenants (they have one store, hence one register) so no special-case logic is needed.

### Why forward-only, no historical backfill

User decision. Z-Reading replay across historical data is technically possible but:
- Voided Z-Readings complicate the picture
- Original deposit/cashflow events may have been recorded against real banks instead of Cash on Hand
- Starting from zero on deploy day is simpler and less error-prone

Owner accepts that the till history starts at deploy and won't reflect anything before that.

### What's NOT in scope (deliberately)

- **Real-time Cash on Hand updates via pos_logs** — see B1 reasoning above
- **Multi-register per store** — one Cash on Hand per store is the constraint
- **Cashier-level sub-accounts** — Z-Readings carry the closer's user_id; that's the attribution mechanism, no separate per-cashier register
- **Auto-classification of cash-outs ("mafisco = owner savings", etc.)** — server-side keyword matching was rejected separately; bot does its own classification
- **payment_type enum on expenses** — deferred indefinitely; cash on hand makes this less urgent because the bot can derive owner-draws from Cash on Hand movements

## Bot surface (OpenClaw)

No new abilities required. All read-side, gated by existing `openclaw:read`.

### Endpoints affected

- `/openclaw/banks/accounts` — returns `store_id` on each bank (additive; null for real banks)
- `/openclaw/banks/balances` — same; bot can sum cash_on_hand banks for total
- `/openclaw/banks/transactions?bank_id={cash_on_hand_id}` — drilldown into till activity
- `/openclaw/snapshot` — extended with:
  - `cash_on_hand_total` (number)
  - `cash_on_hand_by_store` (array of `{store_id, store_name, balance, last_zreading_at, last_deposit_at}`)

### New endpoints

- `GET /openclaw/z-readings?date_from&date_to&store_id&pos_id&limit&cursor` — list Z-Readings, tenant-scoped
- `GET /openclaw/z-readings/{z}` — single Z-Reading with totals, cashier id, linked bank transactions on Cash on Hand
- Optional: `GET /openclaw/z-readings/{z}/reconciliation` — pair the Z with bank deposits in the same date window, returns the discrepancy summary (this is the killer feature — the bot's audit lens)

Each Z-Reading view includes:
- Cashier `user_id` + name (for per-cashier attribution)
- Cash sales, cash-ins, cash-outs, refunds (the inflow/outflow components)
- Linked `bank_transactions` (the Cash on Hand entries triggered by this Z)
- Time window of recorded deposits to real banks (so the bot can match)

## Admin UI changes

### Stores form (create + edit)

- New checkbox: "This location has a cash register (POS sales / cash on hand tracking)" — default checked
- On submit, the StoreObserver auto-creates/renames the linked Cash on Hand bank

### Banks listing

- Cash on Hand rows interleave with real banks (no visual separation per owner's preference)
- Type column label "Cash on Hand" (existing column already shows type — just adds one more value)
- Optional: small icon prefix (🏪 / 💵 / a Metronic till glyph) before the bank name for at-a-glance differentiation
- Name fields disabled on edit form with help text: "This is an auto-managed cash register for **{store_name}**. The name updates when the store renames."
- Delete button hidden on Cash on Hand rows
- Server-side: PATCH that tries to override the name returns 422; DELETE returns 422 with a helpful message

## Theft-prevention signals this unlocks

The bot, post-deploy, can compute and alert on:

1. **Mismatch alerts**: Z-Reading cash sales > recorded till deposit. *"Branch 2 Z-Reading May 12 rang up PHP 47,000 cash; deposits in the next 48 hours totaled PHP 30,000. Cash on Hand balance: PHP 17,000. Is this expected?"*
2. **Stale cash**: Cash on Hand balance > threshold for N days without a deposit. *"Branch 2 has had cash > PHP 30,000 sitting for 6 days. Last deposit: May 7. Want me to flag for collection?"*
3. **Cross-store velocity divergence**: *"Branch 1 turns over cash every 2 days. Branch 2 every 8 days. Same business, same hours — why?"*
4. **Per-cashier discrepancy attribution**: tied to Z-Reading.user_id. *"Cashier Marian's shifts at Branch 1 have shortages in 3 out of 5 closes this month, averaging PHP 1,200 each. Cashier Liza's shifts have zero discrepancies."*
5. **Adjustment frequency**: each `POST /banks/{cash_on_hand}/adjustment` writes an audit_logs row with a `reason`. Repeated writedowns on the same register = pattern.

## Build sequence (for when ready)

Four PRs, each independently shippable, in this order:

### PR 1 — Schema + Store observer + tenant-bootstrap hook

- Migration: add `stores.has_cash_register`, `banks.store_id`, extend `banks.account_type` to allow `5`, add unique index on `(user_id, store_id, account_type)` where `account_type=5`
- Update `SuperAdmin\UserController::store` (tenant bootstrap): create Cash on Hand bank linked to the default Store
- StoreObserver: handles created (auto-create register), updated (toggle + rename), deleted (zero-balance precondition)
- Admin UI: Store form checkbox; banks page name lock + delete-button hide
- Tests: every behavior matrix row

**Output after this PR:** Cash on Hand registers exist for every tenant's stores, but no Z-Reading observer yet, so they all stay at zero balance. Safe to deploy.

### PR 2 — Z-Reading observer + Cash on Hand population

- ZreadingObserver: split deposit + withdrawal on Z create; voiding rolls back
- Bot's `/openclaw/banks/*` endpoints automatically pick up the new bank rows (no controller change needed since they're already tenant-scoped)
- Tests: Z observer fires, voiding Z rolls back, orphan-Z (no register) skips silently, idempotency on retry

**Output after this PR:** Z-Readings start populating the till. Forward-only; deploy day is the start of recorded history.

### PR 3 — `/openclaw/z-readings/*` read endpoints

- `GET /openclaw/z-readings?date_from&date_to&store_id&pos_id&limit&cursor`
- `GET /openclaw/z-readings/{z}`
- Optional: `/reconciliation` endpoint for matched-deposits
- Tests: tenant scoping, filter behavior, drill-in

**Output after this PR:** Bot can answer "did the cashier deposit everything?" directly.

### PR 4 — `/openclaw/snapshot` extensions

- Add `cash_on_hand_total` and `cash_on_hand_by_store` fields
- Add `last_deposit_at` per cash-on-hand record (for staleness signal)
- Tests: snapshot includes the new fields with correct values across multi-store fixtures

**Output after this PR:** Bot's one-shot health-check shows till status across all branches in a single call.

## Open questions for build time

These don't block planning, but need answers when PR 1 starts:

1. **Where exactly is the `StoreController` create/update flow?** Owner mentioned `Controllers/Settings`; need to confirm whether admin-side store creation flows through there or somewhere else (or both, with the SuperAdmin path being separate).
2. **Should `high_balance_threshold` be added alongside the existing `low_balance_threshold` on banks?** For the "stale cash" alert, the bot needs a threshold. Either we let the admin configure per-register, or the bot uses a hardcoded fallback. Per-register is more flexible.
3. **Visual indicator on Cash on Hand rows in admin Banks list** — Metronic icon, emoji, or just the type label? Cosmetic, but worth deciding once.
4. **Soft-delete vs hard-delete of registers when store is deleted** — soft-delete preserves audit history but the register would still show in unfiltered queries. Recommendation: soft-delete with a default scope that excludes them; auditors can use `withTrashed()` to query.

## What to revisit before scheduling the build

- Re-confirm the multi-tenancy plan (`multi_tenancy_enforcement.md`) hasn't shifted — this design assumes tenant scoping via `user_id` continues to be the boundary
- Re-confirm no new Z-Reading-touching feature has shipped in the meantime that would conflict with the new observer
- Verify the apex_pos / apex_dashboard clients don't depend on Cash on Hand being absent from their bank lists (they currently won't see them anyway since bots and POS clients are separate surfaces, but worth a quick check before deploy)

## Linked plans

- `multi_tenancy_enforcement.md` — the broader tenant-scoping discipline this design slots into
- (future) — if/when the `payment_type` schema column on expenses is reconsidered, Cash on Hand provides a clean alternative path (owner-draw = withdrawal from Cash on Hand to a non-bank "owner pocket" virtual account, perhaps `account_type=6 = owner_pocket`). Not in scope here but a clean follow-on.

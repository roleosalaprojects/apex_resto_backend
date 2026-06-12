# apex_dashboard — Locked Unit Approval Label Spec

**Scope:** Render the new `locked_unit` higher-access permission type correctly in the approval list. Smallest of the three repo changes.

**Status:** Backend ready on `feature/unit-locking` branch of `apex_backend`.

**Repo:** `apex_dashboard` (Flutter)

---

## Step 1 — Create a feature branch

```bash
cd apex_dashboard
git checkout main
git pull
git checkout -b feature/locked-units
```

---

## Background

Apex now lets owners lock specific Units of Measure (UoMs) so cashiers can't sell under that UoM without manager approval. The approval prompt flows through the existing higher-access infrastructure — same `HigherAccessRequest` rows, same FCM push, same approve/deny endpoint, **same notification payload shape**. You only need to surface the new `permission_type` value in the UI.

The FCM-recipient gate is based on a new role flag `unit_lock_approve` (managed in the apex_backend web admin). Users without that flag won't receive the push, so dashboard logic doesn't need a per-flag check — the recipient list is server-side.

---

## What changes

### New `permission_type` value

```
locked_unit
```

The dashboard's pending-requests list already pulls `permission_type` from the API. Today it handles five values; now it handles six.

### Context payload

When a cashier triggers a locked-unit request, the `context_data` field on the request contains:

```json
{
  "item_id": 42,
  "item_name": "Premium Rice",
  "unit_id": 7,
  "unit_name": "Sack"
}
```

Worth surfacing in the approval card so the approver knows what they're authorizing.

---

## Step 2 — Find the existing approval-card rendering

Search the dashboard codebase for the existing handling of `permission_type` — likely a `switch` or `match` on the string value (or on an enum if you mirror the POS-side enum). Names to look for:

- `permission_type`
- `discounts`
- `refunds`
- `delete_items`
- `cash_out`
- `credit_sale`

That's where the new arm goes.

---

## Step 3 — Add the new permission type

Add the enum value and label:

```dart
case 'locked_unit':
  return 'Use Locked Unit';
```

If the approval card has an icon mapping, use a lock icon (Material `Icons.lock_outline` or similar) for visual distinction.

---

## Step 4 — Surface the context data

When the request is for `locked_unit`, render the contextual info from `context_data` so the approver sees what they're approving:

```
[🔒 Use Locked Unit]
Premium Rice — Sack
Requested by: <cashier name>
At: <store name>
[ Approve ]  [ Deny ]
```

If the existing approval cards already render `context_data` generically (e.g. for refunds showing the order #), the new permission type fits the same shape — likely no change beyond the label/icon. If `context_data` is currently ignored by the UI, this is a good chance to surface it for all permission types.

---

## Step 5 — Tests

- Approval card for a `locked_unit` request shows the new label
- `context_data` for `locked_unit` (item_name + unit_name) renders correctly
- Approve and Deny still hit the existing respond endpoint with the request_id (no new endpoint)

---

## What does NOT change

- Endpoints — `/v1/pos/higher-access` list and respond are unchanged.
- Approval logic — backend canApprove gate is server-side; dashboard doesn't need to check role flags client-side.
- Notification payload shape — same FCM message structure.

---

## Rollout

Forward-compatible. An old dashboard without this change will simply display "locked_unit" (raw string) or fall through to a default case when it receives one of these requests — functional, just ugly. No emergency rollout. Ship at your normal cadence.

---

## Cross-reference

- Backend implementation lives on `feature/unit-locking` of `apex_backend`.
- POS counterpart: `development/specs/apex_pos_locked_unit_spec.md` (in `apex_backend` repo).
- Backend approval flow: `app/Http/Controllers/API/v1/pos/HigherAccessController.php` — line 255 (`canApprove`) maps `'locked_unit' => $role->unit_lock_approve`.

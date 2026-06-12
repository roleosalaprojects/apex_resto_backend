# apex_pos — Locked Unit Higher-Access Spec

**Scope:** Add support for the new `locked_unit` higher-access permission type so cashiers cannot use a locked Unit of Measure (UoM) without runtime approval from a manager.

**Status:** Backend ready on `feature/unit-locking` branch of `apex_backend`. This work picks up on the apex_pos side.

**Repo:** `apex_pos` (Flutter)

---

## Step 1 — Create a feature branch

Before touching code:

```bash
cd apex_pos
git checkout main         # or whatever the trunk branch is
git pull
git checkout -b feature/locked-units
```

All changes below land on `feature/locked-units`. Open a PR against the trunk when done.

---

## Background — why this exists

Wholesale-style UoMs (e.g. "Sack of 25kg") often sell at a lower per-base-unit price than the loose UoM. Cashiers can abuse this by ringing up retail-size purchases under a wholesale UoM and pocketing the difference, or quietly favoring friends/family with bulk pricing. The web admin can now mark specific UoMs as **locked**, which means selling under that UoM at POS requires a manager-level approval per sale.

The backend reuses the existing higher-access flow you already wired up for discounts/refunds/cash-out/etc. — same `requestHigherAccess` → polling → `onApproved` callback pattern. You only need to add one new enum value, parse one new field, and intercept unit selection in the cart UI.

---

## What the backend exposes

### New `permission_type` accepted by `POST /v1/pos/higher-access`

```text
locked_unit    requires approver role to have unit_lock_approve = true
```

Existing five permission types (`discounts`, `refunds`, `delete_items`, `cash_out`, `credit_sale`) are unchanged.

### New field on item/unit payloads

`/v1/pos/items` and `/v1/pos/units` responses now include `locked` (bool, default `false`) on every `item_units` row:

```json
{
  "id": 42,
  "unit_id": 7,
  "unit_name": "Sack",
  "qty": 25,
  "price": 2500,
  "barcode": "...",
  "locked": true
}
```

A `false` (or missing) value means current behavior — no gate, no prompt. A `true` value means the cashier must request runtime approval before using this UoM.

---

## Step 2 — Add `lockedUnit` to the enum

File: `lib/models/higher_access_request_model.dart`

```dart
enum HigherAccessPermissionType {
  discounts,
  refunds,
  deleteItems,
  cashOut,
  creditSale,
  lockedUnit, // <- new
}
```

Extend the `value` and `displayName` getters and the `fromString` factory:

```dart
case HigherAccessPermissionType.lockedUnit:
  return 'locked_unit';      // value (matches backend permission_type string)
case HigherAccessPermissionType.lockedUnit:
  return 'Use Locked Unit';  // displayName for the waiting dialog
case 'locked_unit':
  return HigherAccessPermissionType.lockedUnit; // fromString
```

---

## Step 3 — Parse `locked` on the ItemUnit model

Find the Dart model that represents an `ItemUnit` row from the API (search for the file that decodes `unit_id`, `qty`, `price`, `barcode`).

Add:

```dart
final bool locked;
// ... in fromJson:
locked: (json['locked'] ?? false) as bool,
```

If the model uses a local SQLite cache, bump the schema and add a `locked` column. If migration support is in place, follow the existing migration pattern; otherwise document a one-time cache wipe required after release.

---

## Step 4 — Intercept locked-unit selection in the cart UI

**Recommended prompt timing: fail-fast on UNIT SELECTION** (not at cart commit).

Why: if no manager is available to approve, the cashier should know immediately, before they've built up a cart. The 120-second polling window gives the manager plenty of time; a 2-minute wait at the unit picker is fine, a 2-minute wait at the "Pay" button after building a 30-line cart is not.

Find the unit picker in the cart flow (likely in `lib/pages/home/tablet/cart_component.dart` or similar). When the user taps a unit on an item:

```dart
if (chosenUnit.locked) {
  ref.read(higherAccessProvider.notifier).requestAccess(
    permissionType: HigherAccessPermissionType.lockedUnit,
    contextData: {
      'item_id': item.id,
      'item_name': item.name,
      'unit_id': chosenUnit.id,
      'unit_name': chosenUnit.name,
    },
    onApproved: () {
      // Only add the line / proceed once the manager approves.
      addLineWithUnit(item, chosenUnit);
    },
  );
  // Show the waiting dialog as you do for the other permission types.
  return;
}

// Not locked: proceed as usual.
addLineWithUnit(item, chosenUnit);
```

The `requestHigherAccess()` service function and `HigherAccessWaitingDialog` widget are already wired — you just need to flow the new permission type through.

---

## Step 5 — Visual treatment

In the unit picker chips/list, render a small 🔒 icon next to any UoM whose `locked == true`. Keeps cashiers from being surprised when they tap it.

```dart
Row(children: [
  Text(unit.name),
  if (unit.locked) const SizedBox(width: 4),
  if (unit.locked) const Icon(Icons.lock_outline, size: 14),
])
```

---

## Step 6 — Tests

Mirror the existing tests for other permission types if any exist. At minimum:

- Unit model decodes `locked: true` from JSON
- Unit model defaults `locked` to `false` when the field is absent (backwards compatibility — older backend versions)
- Tapping a locked unit triggers `requestAccess` with `permissionType: lockedUnit`
- Tapping an unlocked unit does NOT trigger `requestAccess`
- On `onApproved`, the cart line gets added with the locked unit

---

## What does NOT change

- The `requestHigherAccess` service signature, `HigherAccessNotifier`, polling logic, and waiting dialog — all unchanged. You're only adding a new enum case and one new branch in the cart UI.
- Refund / discount / cash-out / delete-item / credit-sale flows — unchanged.
- Other item endpoints — only `item_units` rows gained a `locked` field.

---

## Rollout

Backend is forward-compatible. Old POS clients without this change will simply ignore the new `locked` field and treat every UoM as unlocked (status quo behavior). No emergency rollout needed; ship at your normal cadence.

After release, owners can start locking UoMs from the web admin. Until they do, nothing changes for cashiers.

---

## Cross-reference

- Backend implementation lives on `feature/unit-locking` of `apex_backend`.
- Dashboard counterpart: `development/specs/apex_dashboard_locked_unit_spec.md` (in `apex_backend` repo).
- Backend higher-access controller (for reference): `app/Http/Controllers/API/v1/pos/HigherAccessController.php`.
- Backend ItemUnit model: `app/Models/Products/ItemUnit.php` — has the `locked` field.

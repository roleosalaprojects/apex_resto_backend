# apex_pos — Credit Sale & Credit Payment Higher-Access Spec

**Scope:** Wire two dormant `permission_type` values — `credit_sale` (already in enum, currently uses legacy override only) and `credit_payment` (not in enum yet) — into the modern request-access flow that `discounts` / `refunds` / `locked_unit` already use.

**Status:** Backend plumbing is complete (validation, role-flag mapping, DB enum). POS is the missing piece.

**Repo:** `apex_pos` (Flutter, Riverpod)

**Companion changes (already in place / pending):**
- `apex_backend` `dev`: enum supports `credit_sale`; `credit_payment` migration TBD by backend agent — coordinate before merging this branch.
- `apex_dashboard` `dev` (post `feature/credit-permission-labels` merge): label cases for both types in `permissionLabel` switch.

---

## Step 1 — Create a feature branch

```bash
cd apex_pos
git checkout main
git pull
git checkout -b feature/higher-access-credit-permissions
```

---

## Background

Credit sales (`payment_type=3`) already check `user.role.crdtSale` at `lib/controllers/payments/payment_controller.dart:126-148` — but when the flag is missing, the POS only offers the **legacy override** path (`promptHigherUserAccessOverride`, which prompts for a manager's username + password locally). The modern dashboard-approval flow that `discounts` uses is wired everywhere else but not here.

Credit payments are worse: the menu item itself is hidden when `user.role.crdtPymnt != 1` (`lib/pages/menu_list.dart:152-159`). A cashier without the flag has no way to even request access.

This spec brings both flows in line with the established `discounts` pattern: role-gated, with two escape hatches (legacy override OR request-from-dashboard) when the flag is missing.

---

## Decision summary (locked — do not re-litigate without owner sign-off)

| Question | Decision |
|---|---|
| Trigger condition | **Role-gated.** If `crdtSale=1` / `crdtPymnt=1`, proceed silently. If 0, prompt with the standard two-option dialog. |
| Legacy override path | **Keep.** Same as discounts. Matches user muscle memory; the new path is additive, not a replacement. |
| Credit payment menu visibility | **Change from hidden-when-missing to always-visible.** Gating moves into the action handler so cashiers can request approval. |
| Default context payload | See "Context data contracts" below — locked at design time so dashboard rendering can be pre-built. |

---

## Step 2 — Model: add `creditPayment` to the enum

**File:** `lib/models/higher_access_request_model.dart`

```dart
enum HigherAccessPermissionType {
  discounts,
  refunds,
  deleteItems,
  cashOut,
  creditSale,
  lockedUnit,
  creditPayment,   // ← add
}
```

Update `HigherAccessPermissionTypeExtension`:

```dart
extension HigherAccessPermissionTypeExtension on HigherAccessPermissionType {
  String get value => switch (this) {
    HigherAccessPermissionType.discounts     => 'discounts',
    HigherAccessPermissionType.refunds       => 'refunds',
    HigherAccessPermissionType.deleteItems   => 'delete_items',
    HigherAccessPermissionType.cashOut       => 'cash_out',
    HigherAccessPermissionType.creditSale    => 'credit_sale',
    HigherAccessPermissionType.lockedUnit    => 'locked_unit',
    HigherAccessPermissionType.creditPayment => 'credit_payment',   // ← add
  };

  String get displayName => switch (this) {
    // ... existing cases ...
    HigherAccessPermissionType.creditPayment => 'Credit Payment',   // ← add
  };

  static HigherAccessPermissionType fromString(String value) => switch (value) {
    // ... existing cases ...
    'credit_payment' => HigherAccessPermissionType.creditPayment,   // ← add
    _ => HigherAccessPermissionType.discounts,
  };
}
```

---

## Step 3 — Role mapping in auth_controller (TWO functions, not one)

**File:** `lib/controllers/auth_controller.dart`

### 3a. `_roleToPermissionType()` — used by Request Access path

Around line 429. Add `credit_payment` row; `credit_sale` and `locked_unit` should already be present (confirm).

```dart
HigherAccessPermissionType _roleToPermissionType(String role) {
  return switch (role) {
    'discounts'      => HigherAccessPermissionType.discounts,
    'refunds'        => HigherAccessPermissionType.refunds,
    'delete_items'   => HigherAccessPermissionType.deleteItems,
    'cash_out'       => HigherAccessPermissionType.cashOut,
    'credit_sale'    => HigherAccessPermissionType.creditSale,
    'locked_unit'    => HigherAccessPermissionType.lockedUnit,
    'credit_payment' => HigherAccessPermissionType.creditPayment,   // ← add
    _ => HigherAccessPermissionType.discounts,
  };
}
```

### 3b. `promptHigherUserAccessOverride()` permission check chain — used by OVERRIDE path

**Lines 294-304.** This is the easy-to-miss one. The post-auth check that gates whether the locally-authenticated manager actually has the right is a hand-rolled if/else ladder that today knows only about `discounts / refunds / delete_items / cash_out / credit_sale`. Add two rows:

```dart
} else if (role == 'credit_sale') {
  hasPermission = user.role?.crdtSale == 1;
} else if (role == 'credit_payment') {                    // ← add
  hasPermission = user.role?.crdtPymnt == 1;
} else if (role == 'locked_unit') {                       // ← add (gap noted during this audit)
  hasPermission = user.role?.unitLockApprove == 1;
}
```

**Without these rows, the OVERRIDE button silently fails for credit_payment and locked_unit** — the manager enters valid credentials, the auth succeeds, but `hasPermission` stays false because no branch sets it, and the user sees the "Unauthorized" snack. Pre-existing gap for `locked_unit` worth closing in the same PR.

---

## Step 4 — Credit sale: replace override-only path with the standard dialog

**File:** `lib/controllers/payments/payment_controller.dart`  
**Lines:** 126-148

### Current behavior
```dart
if (user.role?.crdtSale == 1) {
  _proceedWithCreditSale(...);
} else {
  promptHigherUserAccessOverride(
    context,
    role: 'credit_sale',
    onSuccess: () => _proceedWithCreditSale(...),
  );
}
```

### New behavior
Swap the `else` branch to use `promptHigherAccessOption` (the dual-option dialog used for discounts), which itself fans out to either the legacy override OR `HigherAccessNotifier.requestAccess`.

**Real signature** (from `auth_controller.dart:353`):
```dart
Future<void> promptHigherAccessOption({
  required BuildContext context,
  required Function() overrideFunc,
  required String role,
  WidgetRef? ref,
  Map<String, dynamic>? contextData,
})
```

### Required widget refactor
`payment_controller.dart` is currently a regular `StatefulWidget` — it has no Riverpod `WidgetRef` in scope. The Request Access branch inside `promptHigherAccessOption` short-circuits when `ref == null` (line 403), so without this refactor only OVERRIDE would actually do anything — Request Access would appear as a button but no-op silently. **Convert the calling widget to `ConsumerStatefulWidget` / `ConsumerState` and thread `ref` into the call.**

### Call site

```dart
if (user.role?.crdtSale == 1) {
  setState(() {
    paymentPage = (paymentPage == 3) ? 0 : 3;
    cash = 0;
    change = 0;
  });
} else {
  promptHigherAccessOption(
    context: context,
    overrideFunc: () {
      setState(() {
        paymentPage = 3;
        cash = 0;
        change = 0;
      });
    },
    role: 'credit_sale',
    ref: ref,                                      // requires ConsumerState scope
    contextData: _buildCreditSaleContext(),
  );
}
```

`_buildCreditSaleContext()`:

```dart
Map<String, dynamic> _buildCreditSaleContext() {
  return {
    'customer_id':       customer.id,
    'customer_name':     customer.name,
    'amount':            totalAmount,
    'available_credit':  customer.availableCredit,
    'current_balance':   customer.creditBalance,
    'item_count':        cartItems.length,
  };
}
```

---

## Step 5 — Credit payment: surface the menu, gate the action

### 5a. Stop hiding the menu item

**File:** `lib/pages/menu_list.dart`  
**Lines:** 152-159

Remove the `if (user.role?.crdtPymnt == 1)` wrapper around the Credit Payment menu entry. The menu item is now always visible; the gate moves inside the page.

### 5b. Gate the payment action

**File:** `lib/pages/credit/credit_repayment_page.dart`  
**Around line 604** (the "Process Payment" button's `onPressed`)

Wrap the existing process-payment call. As with credit_sale, **convert the page widget to `ConsumerStatefulWidget`** so `ref` is in scope.

```dart
void _onProcessPaymentPressed() {
  if (user.role?.crdtPymnt == 1) {
    _processPayment();
    return;
  }
  promptHigherAccessOption(
    context: context,
    overrideFunc: _processPayment,
    role: 'credit_payment',
    ref: ref,                                      // requires ConsumerState scope
    contextData: _buildCreditPaymentContext(),
  );
}

Map<String, dynamic> _buildCreditPaymentContext() {
  return {
    'customer_id':       _selectedCustomer!.id,
    'customer_name':     _selectedCustomer!.name,
    'amount':            double.tryParse(_amountController.text) ?? 0,
    'current_balance':   _selectedCustomer!.creditBalance,
    'payment_method':    _paymentMethod,
  };
}
```

Don't compute "balance after payment" client-side — that's the backend's truth. The dashboard can compute `current_balance − amount` for display if it wants.

---

## Step 6 — Context data contracts (locked — dashboard depends on these keys)

### `credit_sale`
```json
{
  "customer_id":       17,
  "customer_name":     "Maria Dela Cruz",
  "amount":            1250.00,
  "available_credit":  3000.00,
  "current_balance":   1750.00,
  "item_count":        4
}
```

### `credit_payment`
```json
{
  "customer_id":       17,
  "customer_name":     "Maria Dela Cruz",
  "amount":            500.00,
  "current_balance":   1750.00,
  "payment_method":    "cash"
}
```

Keep keys snake_case to match the existing convention (`item_name`, `unit_name` from the locked_unit spec).

---

## Step 7 — Tests

**Location:** `test/cart/` (mirrors the `locked_unit_test.dart` pattern)

### New files
- `test/cart/credit_sale_test.dart`
- `test/cart/credit_payment_test.dart`

### Per file, mirror the locked_unit test:

```dart
group('HigherAccessPermissionType.creditPayment', () {
  test('value matches backend permission_type string', () {
    expect(HigherAccessPermissionType.creditPayment.value, 'credit_payment');
  });

  test('displayName is human-friendly', () {
    expect(HigherAccessPermissionType.creditPayment.displayName, 'Credit Payment');
  });

  test('fromString round-trips credit_payment', () {
    expect(
      HigherAccessPermissionTypeExtension.fromString('credit_payment'),
      HigherAccessPermissionType.creditPayment,
    );
  });

  test('fromString falls back to discounts for unknown values', () {
    expect(
      HigherAccessPermissionTypeExtension.fromString('not_a_real_perm'),
      HigherAccessPermissionType.discounts,
    );
  });
});
```

### Additional flow tests (widget-level)
- `_onProcessPaymentPressed` proceeds directly when `crdtPymnt=1` (no dialog)
- `_onProcessPaymentPressed` shows `promptHigherAccessOption` when `crdtPymnt=0`
- Same pair for credit_sale in `payment_controller_test.dart`

---

## What does NOT change

- `HigherAccessNotifier.requestAccess()` and the polling logic in `higher_access_provider.dart` — both new permissions reuse it as-is.
- Status endpoint, polling interval (3s), timeout (120s) — same plumbing.
- Legacy override path (`promptHigherUserAccessOverride`) — preserved as one of the two options inside `promptHigherAccessOption`.
- Backend endpoints — the spec assumes `/api/v1/auth/higher-access/request` already accepts `credit_payment` after the backend's pending enum migration. **Block on that landing on `dev` before merging this branch.**

---

## Rollout

1. Backend lands `credit_payment` in the enum + validator + `canApprove()` map. Wait for that on `dev`.
2. POS merges this spec. Cashiers without `crdtSale` / `crdtPymnt` now see the dual-option dialog instead of the legacy-only override (for credit_sale) or hidden menu (for credit_payment).
3. Dashboard already renders the labels (post `feature/credit-permission-labels`). Context-data rendering on the dashboard is optional polish — see "Follow-ups" below.

If the backend enum lands but the POS does not, no behavior changes (legacy paths still work). If the POS lands first, requests with `credit_payment` will be rejected by the backend validator — keep the merge order disciplined.

---

## Follow-ups (out of scope for this spec)

- **Dashboard context rendering**: The dashboard's `_buildContextInfo` in `higher_access_request_card.dart` only specializes on `locked_unit` / `discount` / `refund` today. Once the credit_* payloads start flowing, a small follow-up PR can add nice rendering ("Customer: Maria Dela Cruz — ₱1,250.00, current balance ₱1,750"). Until then the card falls through to `context.toString()` — functional but ugly.
- **Customer credit-limit check at the backend**: the POS shouldn't be the only thing enforcing `availableCredit >= amount` for credit sales. Worth confirming the backend rejects credit sales over the limit independently. Separate spec if not.

---

## Cross-reference

- Existing trigger template: `lib/pages/home/tablet/cart_component.dart:233` (the discounts flow) and `lib/controllers/auth_controller.dart:353-426` (`promptHigherAccessOption`).
- Existing service entry point (unchanged): `lib/providers/higher_access_provider.dart:75-128` (`HigherAccessNotifier.requestAccess`).
- Backend mapping (already in place): `app/Http/Controllers/API/v1/pos/HigherAccessController.php:226` and `:261`. Backend agent needs to add the `credit_payment` row alongside `credit_sale` in both.
- Sibling spec (just shipped): `apex_dashboard_locked_unit_spec.md` — same shape of change on the dashboard side.

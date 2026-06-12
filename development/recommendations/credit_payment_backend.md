# Credit Payment — Backend Implementation Guide

## Overview

Backend handles all credit business logic: validation, balance tracking, ledger entries, payment method management, and permission enforcement. All monetary operations must be wrapped in database transactions.

---

## Migrations

### 1. Add credit fields to customers table

**New file**: `database/migrations/YYYY_MM_DD_000001_add_credit_fields_to_customers_table.php`

```php
Schema::table('customers', function (Blueprint $table) {
    $table->decimal('credit_limit', 15, 2)->default(0)->after('accumulated_points');
    $table->decimal('credit_balance', 15, 2)->default(0)->after('credit_limit');
});
```

### 2. Create credit transactions ledger

**New file**: `database/migrations/YYYY_MM_DD_000002_create_customer_credit_transactions_table.php`

```php
Schema::create('customer_credit_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
    $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
    $table->decimal('amount', 15, 2);
    $table->enum('type', ['charge', 'payment']);
    $table->decimal('balance_after', 15, 2);
    $table->string('payment_method')->nullable(); // cash, e-wallet, bank_transfer, cheque
    $table->string('reference_number')->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->unsignedBigInteger('pos_id')->nullable();
    $table->unsignedBigInteger('store_id')->nullable();
    $table->timestamps();

    $table->index(['customer_id', 'created_at']);
    $table->index(['type']);
});
```

### 3. Add credit permissions to roles table

**New file**: `database/migrations/YYYY_MM_DD_000003_add_credit_permissions_to_roles_table.php`

```php
Schema::table('roles', function (Blueprint $table) {
    $table->tinyInteger('crdt_sale')->default(0)->after('csh_out');
    $table->tinyInteger('crdt_pymnt')->default(0)->after('crdt_sale');
});
```

### 4. Create payment methods table

**New file**: `database/migrations/YYYY_MM_DD_000004_create_payment_methods_table.php`

```php
Schema::create('payment_methods', function (Blueprint $table) {
    $table->id();
    $table->string('name');               // "Bank Transfer"
    $table->string('code')->unique();     // "bank_transfer"
    $table->integer('payment_type');      // Maps to sale.payment_type value
    $table->boolean('is_active')->default(true);
    $table->string('icon')->nullable();   // Icon identifier for POS
    $table->boolean('requires_reference')->default(false);
    $table->unsignedBigInteger('user_id'); // Business owner
    $table->timestamps();

    $table->index(['user_id', 'is_active']);
});
```

### 5. Add credit_sale to higher_access_requests permission_type

**New file**: `database/migrations/YYYY_MM_DD_000005_add_credit_sale_to_higher_access_permission_type.php`

```php
DB::statement("ALTER TABLE higher_access_requests MODIFY COLUMN permission_type ENUM('discounts', 'refunds', 'delete_items', 'cash_out', 'credit_sale')");
```

---

## Models

### CustomerCreditTransaction (New)

**New file**: `app/Models/CustomerRelations/CustomerCreditTransaction.php`

```php
class CustomerCreditTransaction extends Model
{
    protected $fillable = [
        'customer_id', 'sale_id', 'amount', 'type', 'balance_after',
        'payment_method', 'reference_number', 'notes', 'created_by',
        'pos_id', 'store_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function customer(): BelongsTo { ... }
    public function sale(): BelongsTo { ... }
    public function createdByUser(): BelongsTo { ... }
}
```

### PaymentMethod (New)

**New file**: `app/Models/Pos/PaymentMethod.php`

```php
class PaymentMethod extends Model
{
    protected $fillable = [
        'name', 'code', 'payment_type', 'is_active', 'icon',
        'requires_reference', 'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_reference' => 'boolean',
    ];

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeForUser($query, $userId) { return $query->where('user_id', $userId); }
}
```

### Customer Model (Update)

**File**: `app/Models/CustomerRelations/Customer.php`

```php
// Add to $fillable
'credit_limit', 'credit_balance'

// Add casts
'credit_limit' => 'decimal:2',
'credit_balance' => 'decimal:2',

// Add relationship
public function creditTransactions(): HasMany
{
    return $this->hasMany(CustomerCreditTransaction::class);
}

// Add accessor
public function getAvailableCreditAttribute(): float
{
    return (float) $this->credit_limit - (float) $this->credit_balance;
}
```

### Role Model (Update)

**File**: `app/Models/Employees/Role.php`

Add `'crdt_sale'` and `'crdt_pymnt'` to `$fillable`.

### Sale Model (Update)

**File**: `app/Models/Pos/Sale.php`

Document payment_type values:
```php
// Payment Types: 1=Cash, 2=EWallet (GCash/Maya/etc), 3=Credit, 4=BankTransfer, 5=Cheque
```

---

## Controllers

### CustomerCreditController (New)

**New file**: `app/Http/Controllers/API/v1/pos/CustomerCreditController.php`

#### GET `/customers/{customer}/credit-balance`

Returns credit info + recent transactions for a customer.

```php
public function balance(Customer $customer): JsonResponse
{
    return $this->success([
        'credit_limit' => $customer->credit_limit,
        'credit_balance' => $customer->credit_balance,
        'available_credit' => $customer->available_credit,
        'transactions' => $customer->creditTransactions()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'payment_method' => $t->payment_method,
                'reference_number' => $t->reference_number,
                'notes' => $t->notes,
                'created_at' => $t->created_at->toIso8601String(),
            ]),
    ]);
}
```

#### POST `/customers/{customer}/credit-payment`

Process a credit repayment.

```php
public function payment(Request $request, Customer $customer): JsonResponse
{
    $validated = $request->validate([
        'amount' => 'required|numeric|min:0.01',
        'payment_method' => 'required|string|in:cash,e-wallet,bank_transfer,cheque',
        'reference_number' => 'nullable|string|max:255',
        'notes' => 'nullable|string|max:500',
        'pos_id' => 'nullable|integer',
        'store_id' => 'nullable|integer',
    ]);

    if ($validated['amount'] > $customer->credit_balance) {
        return $this->error('Amount exceeds outstanding balance', 422);
    }

    return DB::transaction(function () use ($customer, $validated) {
        $newBalance = $customer->credit_balance - $validated['amount'];

        $customer->update(['credit_balance' => $newBalance]);

        $transaction = CustomerCreditTransaction::create([
            'customer_id' => $customer->id,
            'amount' => $validated['amount'],
            'type' => 'payment',
            'balance_after' => $newBalance,
            'payment_method' => $validated['payment_method'],
            'reference_number' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
            'pos_id' => $validated['pos_id'] ?? null,
            'store_id' => $validated['store_id'] ?? null,
        ]);

        return $this->success([
            'transaction_id' => $transaction->id,
            'new_balance' => $newBalance,
            'available_credit' => $customer->credit_limit - $newBalance,
        ], 'Payment recorded');
    });
}
```

### PaymentMethodController (New)

**New file**: `app/Http/Controllers/API/v1/pos/PaymentMethodController.php`

#### GET `/payment-methods`
Returns active payment methods for the authenticated user's business.

#### POST `/payment-methods` (Dashboard only)
Create/update payment methods.

#### PUT `/payment-methods/{id}/toggle`
Toggle active/inactive state.

### SaleController (Update)

**File**: `app/Http/Controllers/API/v1/pos/SaleController.php`

In `processSale()`, after sale creation, add credit handling:

```php
// After sale is created, inside DB::transaction
if ($request->details['payment_type'] == 3) {
    $customer = Customer::findOrFail($sale->customer_id);
    $newBalance = $customer->credit_balance + $sale->total;

    $customer->update(['credit_balance' => $newBalance]);

    CustomerCreditTransaction::create([
        'customer_id' => $customer->id,
        'sale_id' => $sale->id,
        'amount' => $sale->total,
        'type' => 'charge',
        'balance_after' => $newBalance,
        'created_by' => auth()->id(),
        'pos_id' => $request->details['pos_id'] ?? null,
        'store_id' => $request->details['store_id'] ?? null,
    ]);
}
```

In `refundReceipt()`, add credit reversal:

```php
// When refunding a credit sale (payment_type == 3)
if ($sale->payment_type == 3 && $sale->customer_id) {
    $customer = Customer::find($sale->customer_id);
    if ($customer) {
        $newBalance = max(0, $customer->credit_balance - $sale->total);
        $customer->update(['credit_balance' => $newBalance]);

        CustomerCreditTransaction::create([
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'amount' => $sale->total,
            'type' => 'payment',
            'balance_after' => $newBalance,
            'notes' => "Refund for Sale #{$sale->id}",
            'created_by' => auth()->id(),
        ]);
    }
}
```

### StoreRequest Validation (Update)

**File**: `app/Http/Requests/API/v1/pos/Sale/StoreRequest.php`

Add credit-specific validation:

```php
public function withValidator($validator)
{
    $validator->after(function ($validator) {
        $details = $this->input('details', []);
        if (($details['payment_type'] ?? 0) == 3) {
            $customerId = $details['customer_id'] ?? null;
            if (!$customerId) {
                $validator->errors()->add('details.customer_id', 'A customer must be selected for credit sales.');
                return;
            }
            $customer = \App\Models\CustomerRelations\Customer::find($customerId);
            if (!$customer || $customer->credit_limit <= 0) {
                $validator->errors()->add('details.customer_id', 'Customer is not eligible for credit.');
                return;
            }
            $total = $details['total'] ?? 0;
            if ($total > $customer->available_credit) {
                $validator->errors()->add('details.total', 'Sale total exceeds available credit.');
            }
        }
    });
}
```

### HigherAccessController (Update)

**File**: `app/Http/Controllers/API/v1/pos/HigherAccessController.php`

- Add `'credit_sale'` to `Rule::in()` validation in `store()`
- Add `'credit_sale' => 'crdt_sale'` to FCM notification mapping
- Add `'credit_sale' => (bool) $role->crdt_sale` to `canApprove()`

### CustomerResource (Update)

**File**: `app/Http/Resources/CustomerResource.php`

Add credit fields to `toArray()`:
```php
'credit_limit' => $this->credit_limit,
'credit_balance' => $this->credit_balance,
'available_credit' => $this->available_credit,
```

---

## Routes

**File**: `routes/api/pos.php`

```php
// Credit
Route::get('customers/{customer}/credit-balance', [CustomerCreditController::class, 'balance']);
Route::post('customers/{customer}/credit-payment', [CustomerCreditController::class, 'payment']);

// Payment methods
Route::get('payment-methods', [PaymentMethodController::class, 'index']);
Route::post('payment-methods', [PaymentMethodController::class, 'store']);
Route::put('payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggle']);
```

---

## Seeder

**New file**: `database/seeders/PaymentMethodSeeder.php`

Seed default payment methods per business user:

```php
$methods = [
    ['name' => 'Cash', 'code' => 'cash', 'payment_type' => 1, 'is_active' => true, 'requires_reference' => false],
    ['name' => 'E-Wallet', 'code' => 'e_wallet', 'payment_type' => 2, 'is_active' => true, 'requires_reference' => true],
    ['name' => 'Credit', 'code' => 'credit', 'payment_type' => 3, 'is_active' => true, 'requires_reference' => false],
    ['name' => 'Bank Transfer', 'code' => 'bank_transfer', 'payment_type' => 4, 'is_active' => false, 'requires_reference' => true],
    ['name' => 'Cheque', 'code' => 'cheque', 'payment_type' => 5, 'is_active' => false, 'requires_reference' => true],
];
```

---

## Verification

1. `php artisan migrate` — all migrations run without errors
2. Credit sale with sufficient credit → sale created, balance incremented, ledger entry
3. Credit sale with insufficient credit → 422 validation error
4. Credit sale without customer → validation error
5. Credit repayment → balance decremented, ledger entry
6. Repayment exceeding balance → 422 error
7. Refund of credit sale → balance reversed, ledger entry
8. Payment methods CRUD works
9. Higher access for credit_sale permission works
10. Customer resource returns credit fields

# E-Commerce Module Implementation Plan

## Overview
Create an e-commerce module with Livewire frontend and API for mobile apps. Users must log in to add products to cart. Payments are manually verified by employees.

## User Flow
1. User logs in → browses products → adds to cart → creates order
2. Order status: **Pending** → employee confirms → **Processing** → **Confirmed**
3. User pays (e-wallet/bank/on-premise) → employee verifies → **Paid** → **Ready**
4. Pickup or delivery → **Completed** (or **Cancelled** at any stage)

---

## Phase 1: Foundation Setup

### 1.1 Install Livewire 3
```bash
vendor/bin/sail composer require livewire/livewire
vendor/bin/sail artisan livewire:publish --config
```

### 1.2 Create Enums

**File: `app/Enums/EcommerceOrderStatus.php`**
```php
<?php

namespace App\Enums;

enum EcommerceOrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Ready = 'ready';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Confirmed => 'Confirmed',
            self::Paid => 'Paid',
            self::Ready => 'Ready for Pickup/Delivery',
            self::Shipped => 'Shipped',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::Confirmed => 'primary',
            self::Paid => 'success',
            self::Ready => 'success',
            self::Shipped => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
```

**File: `app/Enums/PaymentStatus.php`**
```php
<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Unpaid => 'Unpaid',
            self::PendingVerification => 'Pending Verification',
            self::Verified => 'Verified',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
        };
    }
}
```

**File: `app/Enums/PaymentMethod.php`**
```php
<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Ewallet = 'ewallet';
    case BankTransfer = 'bank_transfer';
    case CashOnPickup = 'cash_on_pickup';
    case CashOnDelivery = 'cash_on_delivery';

    public function label(): string
    {
        return match($this) {
            self::Ewallet => 'E-Wallet',
            self::BankTransfer => 'Bank Transfer',
            self::CashOnPickup => 'Cash on Pickup',
            self::CashOnDelivery => 'Cash on Delivery',
        };
    }
}
```

**File: `app/Enums/DeliveryType.php`**
```php
<?php

namespace App\Enums;

enum DeliveryType: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match($this) {
            self::Pickup => 'Store Pickup',
            self::Delivery => 'Home Delivery',
        };
    }
}
```

---

### 1.3 Database Migrations

**Create migrations with:**
```bash
vendor/bin/sail artisan make:migration create_ecommerce_orders_table
vendor/bin/sail artisan make:migration create_ecommerce_order_lines_table
vendor/bin/sail artisan make:migration create_ecommerce_carts_table
vendor/bin/sail artisan make:migration create_ecommerce_cart_items_table
vendor/bin/sail artisan make:migration create_ecommerce_payments_table
vendor/bin/sail artisan make:migration create_ecommerce_transaction_logs_table
vendor/bin/sail artisan make:migration create_shipping_addresses_table
vendor/bin/sail artisan make:migration add_ecommerce_permissions_to_roles_table
```

**Migration: `create_ecommerce_orders_table`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Amounts
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Status
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->string('delivery_type')->default('pickup');

            // Addresses (JSON)
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();

            // Notes
            $table->text('customer_notes')->nullable();
            $table->text('employee_notes')->nullable();

            // Workflow timestamps
            $table->foreignId('confirmed_by')->nullable()->constrained('admin')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('admin')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('ready_by')->nullable()->constrained('admin')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();

            $table->foreignId('shipped_by')->nullable()->constrained('admin')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();

            $table->foreignId('completed_by')->nullable()->constrained('admin')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('admin')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_orders');
    }
};
```

**Migration: `create_ecommerce_order_lines_table`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_unit_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot of item at time of order
            $table->string('item_name');
            $table->string('item_barcode')->nullable();
            $table->string('unit_name')->nullable();
            $table->integer('unit_qty')->default(1);

            // Pricing
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_lines');
    }
};
```

**Migration: `create_ecommerce_carts_table`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique('user_id'); // One cart per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_carts');
    }
};
```

**Migration: `create_ecommerce_cart_items_table`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(['ecommerce_cart_id', 'item_id', 'item_unit_id'], 'cart_item_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_cart_items');
    }
};
```

**Migration: `create_ecommerce_payments_table`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_order_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method');
            $table->string('reference_number')->nullable();
            $table->decimal('amount', 12, 2);
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('proof_image')->nullable();
            $table->string('status')->default('pending');

            $table->foreignId('verified_by')->nullable()->constrained('admin')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_payments');
    }
};
```

**Migration: `create_ecommerce_transaction_logs_table`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable'); // loggable_type, loggable_id
            $table->string('action'); // created, status_changed, payment_verified, etc.
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_transaction_logs');
    }
};
```

**Migration: `create_shipping_addresses_table`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->string('recipient_name');
            $table->string('phone');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('province');
            $table->string('postal_code');
            $table->string('country')->default('Philippines');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_addresses');
    }
};
```

**Migration: `add_ecommerce_permissions_to_roles_table`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('ecom')->default(false)->after('spplrs_delete');
            $table->boolean('ecom_orders_read')->default(false)->after('ecom');
            $table->boolean('ecom_orders_update')->default(false)->after('ecom_orders_read');
            $table->boolean('ecom_payments_verify')->default(false)->after('ecom_orders_update');
            $table->boolean('ecom_settings')->default(false)->after('ecom_payments_verify');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn([
                'ecom',
                'ecom_orders_read',
                'ecom_orders_update',
                'ecom_payments_verify',
                'ecom_settings',
            ]);
        });
    }
};
```

---

## Phase 2: Models

### Create Models with:
```bash
vendor/bin/sail artisan make:model EcommerceOrder -f
vendor/bin/sail artisan make:model EcommerceOrderLine -f
vendor/bin/sail artisan make:model EcommerceCart -f
vendor/bin/sail artisan make:model EcommerceCartItem -f
vendor/bin/sail artisan make:model EcommercePayment -f
vendor/bin/sail artisan make:model EcommerceTransactionLog
vendor/bin/sail artisan make:model ShippingAddress -f
```

**Model: `app/Models/EcommerceOrder.php`**
```php
<?php

namespace App\Models;

use App\Enums\DeliveryType;
use App\Enums\EcommerceOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EcommerceOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'user_id',
        'customer_id',
        'store_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'shipping_amount',
        'total',
        'status',
        'payment_status',
        'payment_method',
        'delivery_type',
        'shipping_address',
        'billing_address',
        'customer_notes',
        'employee_notes',
        'confirmed_by',
        'confirmed_at',
        'paid_at',
        'verified_by',
        'verified_at',
        'ready_by',
        'ready_at',
        'shipped_by',
        'shipped_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => EcommerceOrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'delivery_type' => DeliveryType::class,
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'ready_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'EC';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return "{$prefix}{$date}{$random}";
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EcommerceOrderLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EcommercePayment::class);
    }

    public function transactionLogs(): MorphMany
    {
        return $this->morphMany(EcommerceTransactionLog::class, 'loggable');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function readyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_by');
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
```

**Model: `app/Models/EcommerceOrderLine.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'ecommerce_order_id',
        'item_id',
        'item_unit_id',
        'item_name',
        'item_barcode',
        'unit_name',
        'unit_qty',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function itemUnit(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class);
    }
}
```

**Model: `app/Models/EcommerceCart.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcommerceCartItem::class);
    }

    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->item->price;
        });
    }

    public function getItemCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }
}
```

**Model: `app/Models/EcommerceCartItem.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'ecommerce_cart_id',
        'item_id',
        'item_unit_id',
        'quantity',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcommerceCart::class, 'ecommerce_cart_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function itemUnit(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class);
    }

    public function getLineTotalAttribute(): float
    {
        $price = $this->itemUnit ? $this->itemUnit->price : $this->item->price;
        return $this->quantity * $price;
    }
}
```

**Model: `app/Models/EcommercePayment.php`**
```php
<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommercePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ecommerce_order_id',
        'payment_method',
        'reference_number',
        'amount',
        'bank_id',
        'proof_image',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
```

**Model: `app/Models/EcommerceTransactionLog.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EcommerceTransactionLog extends Model
{
    protected $fillable = [
        'loggable_type',
        'loggable_id',
        'action',
        'description',
        'old_values',
        'new_values',
        'user_id',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Model: `app/Models/ShippingAddress.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_default',
        'recipient_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'postal_code',
        'country',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->province,
            $this->postal_code,
        ]);
        return implode(', ', $parts);
    }
}
```

### Update User Model - Add relationships:
```php
// Add to app/Models/User.php

public function ecommerceCart(): HasOne
{
    return $this->hasOne(EcommerceCart::class);
}

public function ecommerceOrders(): HasMany
{
    return $this->hasMany(EcommerceOrder::class);
}

public function shippingAddresses(): HasMany
{
    return $this->hasMany(ShippingAddress::class);
}

public function defaultShippingAddress(): HasOne
{
    return $this->hasOne(ShippingAddress::class)->where('is_default', true);
}
```

### Update Role Model - Add fillable fields:
```php
// Add to $fillable in app/Models/Role.php
'ecom',
'ecom_orders_read',
'ecom_orders_update',
'ecom_payments_verify',
'ecom_settings',
```

---

## Phase 3: Livewire Components

### 3.1 Shop Layout
Create file: `resources/views/shop/layout.blade.php`

### 3.2 Create Livewire Components:
```bash
# Shop Components
vendor/bin/sail artisan make:livewire Shop/ProductCatalog
vendor/bin/sail artisan make:livewire Shop/ProductCard
vendor/bin/sail artisan make:livewire Shop/ProductDetail
vendor/bin/sail artisan make:livewire Shop/CategoryFilter
vendor/bin/sail artisan make:livewire Shop/SearchBar

# Cart Components
vendor/bin/sail artisan make:livewire Cart/CartPage
vendor/bin/sail artisan make:livewire Cart/CartItem
vendor/bin/sail artisan make:livewire Cart/CartSummary
vendor/bin/sail artisan make:livewire Cart/MiniCart

# Checkout Components
vendor/bin/sail artisan make:livewire Checkout/CheckoutPage
vendor/bin/sail artisan make:livewire Checkout/AddressForm
vendor/bin/sail artisan make:livewire Checkout/DeliveryOptions
vendor/bin/sail artisan make:livewire Checkout/PaymentMethodSelector
vendor/bin/sail artisan make:livewire Checkout/OrderReview
vendor/bin/sail artisan make:livewire Checkout/PaymentInstructions

# Customer Components
vendor/bin/sail artisan make:livewire Customer/OrderHistory
vendor/bin/sail artisan make:livewire Customer/OrderDetail
vendor/bin/sail artisan make:livewire Customer/SavedAddresses

# Admin Components
vendor/bin/sail artisan make:livewire Admin/Ecommerce/OrdersDashboard
vendor/bin/sail artisan make:livewire Admin/Ecommerce/OrdersTable
vendor/bin/sail artisan make:livewire Admin/Ecommerce/OrderManagement
vendor/bin/sail artisan make:livewire Admin/Ecommerce/PaymentVerification
```

---

## Phase 4: Web Routes

Add to `routes/web.php`:
```php
use App\Livewire\Shop\ProductCatalog;
use App\Livewire\Shop\ProductDetail;
use App\Livewire\Cart\CartPage;
use App\Livewire\Checkout\CheckoutPage;
use App\Livewire\Customer\OrderHistory;
use App\Livewire\Customer\OrderDetail;
use App\Livewire\Customer\SavedAddresses;
use App\Livewire\Admin\Ecommerce\OrdersDashboard;
use App\Livewire\Admin\Ecommerce\OrdersTable;
use App\Livewire\Admin\Ecommerce\OrderManagement;
use App\Livewire\Admin\Ecommerce\PaymentVerification;

// Public Shop
Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('/', ProductCatalog::class)->name('index');
    Route::get('/category/{category}', ProductCatalog::class)->name('category');
    Route::get('/product/{item}', ProductDetail::class)->name('product');
});

// Authenticated Customer
Route::middleware('auth')->group(function () {
    Route::get('/cart', CartPage::class)->name('cart');
    Route::get('/checkout', CheckoutPage::class)->name('checkout');

    Route::prefix('my-account')->name('account.')->group(function () {
        Route::get('/orders', OrderHistory::class)->name('orders');
        Route::get('/orders/{order}', OrderDetail::class)->name('orders.show');
        Route::get('/addresses', SavedAddresses::class)->name('addresses');
    });
});

// Employee E-commerce Management
Route::middleware('auth')->prefix('ecommerce')->name('ecommerce.')->group(function () {
    Route::get('/dashboard', OrdersDashboard::class)->name('dashboard');
    Route::get('/orders', OrdersTable::class)->name('orders.index');
    Route::get('/orders/{order}', OrderManagement::class)->name('orders.show');
    Route::get('/payments', PaymentVerification::class)->name('payments.index');
});
```

---

## Phase 5: API Endpoints

### Create API Controllers:
```bash
vendor/bin/sail artisan make:controller API/v1/ecommerce/AuthController
vendor/bin/sail artisan make:controller API/v1/ecommerce/ProductController
vendor/bin/sail artisan make:controller API/v1/ecommerce/CategoryController
vendor/bin/sail artisan make:controller API/v1/ecommerce/CartController
vendor/bin/sail artisan make:controller API/v1/ecommerce/CheckoutController
vendor/bin/sail artisan make:controller API/v1/ecommerce/OrderController
vendor/bin/sail artisan make:controller API/v1/ecommerce/PaymentController
vendor/bin/sail artisan make:controller API/v1/ecommerce/AddressController
vendor/bin/sail artisan make:controller API/v1/ecommerce/StoreController
```

### Create API Resources:
```bash
vendor/bin/sail artisan make:resource Ecommerce/ProductResource
vendor/bin/sail artisan make:resource Ecommerce/ProductCollection --collection
vendor/bin/sail artisan make:resource Ecommerce/CartResource
vendor/bin/sail artisan make:resource Ecommerce/CartItemResource
vendor/bin/sail artisan make:resource Ecommerce/OrderResource
vendor/bin/sail artisan make:resource Ecommerce/OrderLineResource
vendor/bin/sail artisan make:resource Ecommerce/PaymentResource
vendor/bin/sail artisan make:resource Ecommerce/AddressResource
```

### Add API Routes to `routes/api.php`:
```php
use App\Http\Controllers\API\v1\ecommerce\AuthController as EcomAuthController;
use App\Http\Controllers\API\v1\ecommerce\ProductController as EcomProductController;
use App\Http\Controllers\API\v1\ecommerce\CategoryController as EcomCategoryController;
use App\Http\Controllers\API\v1\ecommerce\CartController as EcomCartController;
use App\Http\Controllers\API\v1\ecommerce\CheckoutController as EcomCheckoutController;
use App\Http\Controllers\API\v1\ecommerce\OrderController as EcomOrderController;
use App\Http\Controllers\API\v1\ecommerce\PaymentController as EcomPaymentController;
use App\Http\Controllers\API\v1\ecommerce\AddressController as EcomAddressController;
use App\Http\Controllers\API\v1\ecommerce\StoreController as EcomStoreController;

Route::prefix('v1/ecommerce')->name('api.ecommerce.')->group(function () {
    // Public
    Route::post('/register', [EcomAuthController::class, 'register'])->name('register');
    Route::post('/login', [EcomAuthController::class, 'login'])->name('login');

    Route::get('/products', [EcomProductController::class, 'index'])->name('products.index');
    Route::get('/products/search', [EcomProductController::class, 'search'])->name('products.search');
    Route::get('/products/{item}', [EcomProductController::class, 'show'])->name('products.show');
    Route::get('/categories', [EcomCategoryController::class, 'index'])->name('categories.index');
    Route::get('/stores', [EcomStoreController::class, 'index'])->name('stores.index');

    // Authenticated
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [EcomAuthController::class, 'logout'])->name('logout');
        Route::get('/user', [EcomAuthController::class, 'user'])->name('user');

        // Cart
        Route::get('/cart', [EcomCartController::class, 'index'])->name('cart.index');
        Route::post('/cart/items', [EcomCartController::class, 'addItem'])->name('cart.add');
        Route::put('/cart/items/{cartItem}', [EcomCartController::class, 'updateItem'])->name('cart.update');
        Route::delete('/cart/items/{cartItem}', [EcomCartController::class, 'removeItem'])->name('cart.remove');
        Route::delete('/cart', [EcomCartController::class, 'clear'])->name('cart.clear');

        // Checkout
        Route::post('/checkout', [EcomCheckoutController::class, 'store'])->name('checkout.store');
        Route::get('/payment-methods', [EcomCheckoutController::class, 'paymentMethods'])->name('checkout.payment-methods');

        // Orders
        Route::get('/orders', [EcomOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [EcomOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/cancel', [EcomOrderController::class, 'cancel'])->name('orders.cancel');

        // Payment
        Route::post('/orders/{order}/payment', [EcomPaymentController::class, 'store'])->name('payment.store');

        // Addresses
        Route::apiResource('addresses', EcomAddressController::class);
    });
});
```

---

## Phase 6: Form Requests

```bash
vendor/bin/sail artisan make:request Ecommerce/AddToCartRequest
vendor/bin/sail artisan make:request Ecommerce/UpdateCartItemRequest
vendor/bin/sail artisan make:request Ecommerce/CheckoutRequest
vendor/bin/sail artisan make:request Ecommerce/SubmitPaymentRequest
vendor/bin/sail artisan make:request Ecommerce/CreateAddressRequest
vendor/bin/sail artisan make:request Ecommerce/UpdateAddressRequest
vendor/bin/sail artisan make:request Ecommerce/UpdateOrderStatusRequest
```

---

## Phase 7: Testing

```bash
vendor/bin/sail artisan make:test Ecommerce/CartTest
vendor/bin/sail artisan make:test Ecommerce/CheckoutTest
vendor/bin/sail artisan make:test Ecommerce/OrderTest
vendor/bin/sail artisan make:test Ecommerce/PaymentVerificationTest
vendor/bin/sail artisan make:test API/v1/ecommerce/AuthControllerTest
vendor/bin/sail artisan make:test API/v1/ecommerce/ProductControllerTest
vendor/bin/sail artisan make:test API/v1/ecommerce/CartControllerTest
vendor/bin/sail artisan make:test API/v1/ecommerce/OrderControllerTest
```

---

## Implementation Checklist

### Sprint 1: Foundation
- [ ] Install Livewire 3
- [ ] Create enums
- [ ] Create migrations
- [ ] Create models
- [ ] Run migrations
- [ ] Update User model relationships
- [ ] Update Role model

### Sprint 2: Cart & Products
- [ ] Create shop layout
- [ ] ProductCatalog component
- [ ] ProductCard component
- [ ] ProductDetail component
- [ ] CategoryFilter component
- [ ] SearchBar component
- [ ] CartPage component
- [ ] CartItem component
- [ ] MiniCart component
- [ ] Set up shop routes
- [ ] Write tests

### Sprint 3: Checkout & Orders
- [ ] CheckoutPage component
- [ ] AddressForm component
- [ ] DeliveryOptions component
- [ ] PaymentMethodSelector component
- [ ] OrderReview component
- [ ] PaymentInstructions component
- [ ] OrderHistory component
- [ ] OrderDetail component
- [ ] SavedAddresses component
- [ ] Write tests

### Sprint 4: Employee Management
- [ ] OrdersDashboard component
- [ ] OrdersTable component
- [ ] OrderManagement component
- [ ] PaymentVerification component
- [ ] Write tests

### Sprint 5: API Layer
- [ ] Create API Resources
- [ ] Create Form Requests
- [ ] Create API Controllers
- [ ] Set up API routes
- [ ] Write API tests

### Sprint 6: Polish
- [ ] Transaction logging
- [ ] Performance optimization
- [ ] Final testing

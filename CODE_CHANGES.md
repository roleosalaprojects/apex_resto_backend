# Code Changes - Old vs New

This document shows all code changes made during the API standardization update.

---

## 1. ApiResponse Trait (NEW FILE)

**File:** `app/Http/Traits/ApiResponse.php`

```php
<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }
}
```

---

## 2. Controller Changes

### Pattern Applied to All 31 Controllers

**OLD:**
```php
<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('status', true)->get();
        return response()->json($categories);
    }

    public function show($id)
    {
        $category = Category::find($id);
        return response()->json($category);
    }
}
```

**NEW:**
```php
<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Traits\ApiResponse;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = Category::where('status', true)->get();
        return $this->success(CategoryResource::collection($categories));
    }

    public function show($id): JsonResponse
    {
        $category = Category::find($id);
        return $this->success(new CategoryResource($category));
    }
}
```

---

## 3. API Resources (NEW FILES)

### UserResource
**File:** `app/Http/Resources/UserResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role_id' => $this->role_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'is_customer' => $this->is_customer,
            'role' => new RoleResource($this->whenLoaded('role')),
            'details' => new EmployeeResource($this->whenLoaded('details')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### RoleResource
**File:** `app/Http/Resources/RoleResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
```

### EmployeeResource
**File:** `app/Http/Resources/EmployeeResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'phone' => $this->phone,
            'address' => $this->address,
            'image' => $this->image,
        ];
    }
}
```

### CategoryResource
**File:** `app/Http/Resources/CategoryResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
```

### ItemResource
**File:** `app/Http/Resources/ItemResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'price' => $this->price,
            'cost' => $this->cost,
            'stocks' => $this->stocks,
            'status' => $this->status,
            'image' => $this->image,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### StoreResource
**File:** `app/Http/Resources/StoreResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'status' => $this->status,
        ];
    }
}
```

### CustomerResource
**File:** `app/Http/Resources/CustomerResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'points' => $this->points,
            'status' => $this->status,
        ];
    }
}
```

### SaleResource
**File:** `app/Http/Resources/SaleResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'son' => $this->son,
            'total' => $this->total,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'lines' => SaleLineResource::collection($this->whenLoaded('lines')),
            'sold_by' => new UserResource($this->whenLoaded('soldBy')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### SaleLineResource
**File:** `app/Http/Resources/SaleLineResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'item_id' => $this->item_id,
            'item' => new ItemResource($this->whenLoaded('item')),
            'qty' => $this->qty,
            'price' => $this->price,
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit'),
        ];
    }
}
```

### PurchaseResource
**File:** `app/Http/Resources/PurchaseResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier'),
            'total' => $this->total,
            'lines' => PurchaseLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### PurchaseLineResource
**File:** `app/Http/Resources/PurchaseLineResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_id' => $this->purchase_id,
            'item_id' => $this->item_id,
            'item' => new ItemResource($this->whenLoaded('item')),
            'qty' => $this->qty,
            'cost' => $this->cost,
        ];
    }
}
```

---

## 4. Form Request Classes (NEW FILES)

### LoginRequest
**File:** `app/Http/Requests/API/v1/Auth/LoginRequest.php`

```php
<?php

namespace App\Http\Requests\API\v1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.',
        ];
    }
}
```

### Sale\StoreRequest
**File:** `app/Http/Requests/API/v1/pos/Sale/StoreRequest.php`

```php
<?php

namespace App\Http\Requests\API\v1\pos\Sale;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'At least one item is required.',
            'lines.*.item_id.required' => 'Each line must have an item.',
            'lines.*.item_id.exists' => 'Selected item does not exist.',
            'lines.*.qty.required' => 'Quantity is required for each item.',
            'lines.*.qty.min' => 'Quantity must be greater than zero.',
            'lines.*.price.required' => 'Price is required for each item.',
        ];
    }
}
```

---

## 5. Bug Fixes

### 5.1 File Rename: RceiptController → ReceiptController

**OLD:** `app/Http/Controllers/API/v1/pos/RceiptController.php`
**NEW:** `app/Http/Controllers/API/v1/pos/ReceiptController.php`

---

### 5.2 RegisterController - Table Reference Fix

**File:** `app/Http/Controllers/Auth/RegisterController.php`

**OLD:**
```php
protected function validator(array $data)
{
    return Validator::make($data, [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:admin'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);
}
```

**NEW:**
```php
protected function validator(array $data)
{
    return Validator::make($data, [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);
}
```

---

### 5.3 ReadingController - SQL Join Fix

**File:** `app/Http/Controllers/API/v1/pos/ReadingController.php`

**OLD:**
```php
$readings = DB::table('readings as r')
    ->leftJoin('admin as u', 'u.id', 'r.user_id')
    ->select('r.*', 'u.name as cashier')
    ->get();
```

**NEW:**
```php
$readings = DB::table('readings as r')
    ->leftJoin('users as u', 'u.id', 'r.user_id')
    ->select('r.*', 'u.name as cashier')
    ->get();
```

---

### 5.4 UserController - Table Reference Fix

**File:** `app/Http/Controllers/UserController.php`

**OLD:**
```php
$users = DB::table('admin as u')
    ->leftJoin('employees as e', 'e.user_id', 'u.id')
    ->leftJoin('roles as r', 'r.id', 'u.role_id')
    ->select('u.*', 'e.phone', 'e.address', 'r.name as role_name')
    ->get();
```

**NEW:**
```php
$users = DB::table('users as u')
    ->leftJoin('employees as e', 'e.user_id', 'u.id')
    ->leftJoin('roles as r', 'r.id', 'u.role_id')
    ->select('u.*', 'e.phone', 'e.address', 'r.name as role_name')
    ->get();
```

---

### 5.5 ProfileController - Table Reference Fix

**File:** `app/Http/Controllers/Admin/ProfileController.php`

**OLD:**
```php
$profile = DB::table('admin as u')
    ->leftJoin('employees as e', 'e.user_id', 'u.id')
    ->where('u.id', auth()->user()->id)
    ->select('u.*', 'e.phone', 'e.address')
    ->first();
```

**NEW:**
```php
$profile = DB::table('users as u')
    ->leftJoin('employees as e', 'e.user_id', 'u.id')
    ->where('u.id', auth()->user()->id)
    ->select('u.*', 'e.phone', 'e.address')
    ->first();
```

---

## 6. Test Changes

### Pattern for Test Assertions

**OLD:**
```php
public function test_can_list_categories(): void
{
    Category::factory()->count(3)->create(['status' => true]);

    Passport::actingAs($this->user);

    $response = $this->getJson('/api/v1/categories');

    $response->assertStatus(200);
    $response->assertJsonCount(3);
}
```

**NEW:**
```php
public function test_can_list_categories(): void
{
    Category::factory()->count(3)->create(['status' => true]);

    Passport::actingAs($this->user);

    $response = $this->getJson('/api/v1/categories');

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');  // Data now wrapped in 'data' key
}
```

---

### ReportControllerTest - Structure Assertion

**File:** `tests/Feature/API/v1/mobile/ReportControllerTest.php`

**OLD:**
```php
$response->assertJsonStructure([
    'sales',
    'chart',
    'receipts',
]);
```

**NEW:**
```php
$response->assertJsonStructure([
    'success',
    'data' => [
        'sales',
        'chart',
        'receipts',
    ],
]);
```

---

### ItemControllerTest - Structure Assertion

**File:** `tests/Feature/API/v1/pos/ItemControllerTest.php`

**OLD:**
```php
$response->assertJsonStructure([
    'success',
    'products',
]);
```

**NEW:**
```php
$response->assertJsonStructure([
    'success',
    'data' => [
        'products',
    ],
]);
```

---

### UserControllerTest - Table Reference

**File:** `tests/Feature/Admin/UserControllerTest.php`

**OLD:**
```php
$this->assertDatabaseHas('admin', [
    'id' => $user->id,
    'status' => true,
]);
```

**NEW:**
```php
$this->assertDatabaseHas('users', [
    'id' => $user->id,
    'status' => true,
]);
```

---

## 7. Response Format Changes

### Before (Inconsistent)

```json
// Some endpoints returned raw data
[
    {"id": 1, "name": "Beverages"},
    {"id": 2, "name": "Snacks"}
]

// Others returned with success flag
{
    "success": true,
    "categories": [...]
}

// Others returned different structures
{
    "data": [...],
    "message": "Success"
}
```

### After (Standardized)

```json
// All endpoints now return consistent format
{
    "success": true,
    "message": null,
    "data": [
        {"id": 1, "name": "Beverages", "status": true},
        {"id": 2, "name": "Snacks", "status": true}
    ]
}

// Error responses
{
    "success": false,
    "message": "Resource not found",
    "errors": null
}

// Created responses (201)
{
    "success": true,
    "message": "Created successfully",
    "data": {"id": 1, "name": "New Item"}
}
```

---

# Customer Authentication System (E-commerce) - 2026-01-04

## 8. Migration - Add Auth Fields to Customers

**File:** `database/migrations/2026_01_04_183649_add_auth_fields_to_customers_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->timestamp('email_verified_at')->nullable()->after('password');
            $table->rememberToken()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['password', 'email_verified_at', 'remember_token']);
        });
    }
};
```

---

## 9. Customer Model Changes

**File:** `app/Models/Customer.php`

**OLD:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'code',
        // ... other fields
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
```

**NEW:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'code',
        'phone',
        'address',
        'email',
        'password',
        // ... other fields
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
```

---

## 10. Auth Configuration Changes

**File:** `config/auth.php`

**Added Guards:**
```php
'guards' => [
    // ... existing guards
    'customer' => [
        'driver' => 'session',
        'provider' => 'customers',
    ],
    'customer-api' => [
        'driver' => 'passport',
        'provider' => 'customers',
    ],
],
```

**Added Provider:**
```php
'providers' => [
    // ... existing providers
    'customers' => [
        'driver' => 'eloquent',
        'model' => App\Models\Customer::class,
    ],
],
```

**Added Password Reset:**
```php
'passwords' => [
    // ... existing configs
    'customers' => [
        'provider' => 'customers',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

---

## 11. Middleware (NEW FILES)

### CustomerAuthenticate
**File:** `app/Http/Middleware/CustomerAuthenticate.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('customer')->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect()->guest(route('customer.login'));
        }

        return $next($request);
    }
}
```

### CustomerApiAuthenticate
**File:** `app/Http/Middleware/CustomerApiAuthenticate.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerApiAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('customer-api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}
```

### RedirectIfCustomerAuthenticated
**File:** `app/Http/Middleware/RedirectIfCustomerAuthenticated.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfCustomerAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('customer.dashboard');
        }

        return $next($request);
    }
}
```

---

## 12. Bootstrap/App.php Changes

**File:** `bootstrap/app.php`

**OLD:**
```php
->withMiddleware(function (Middleware $middleware): void {
    //
})
```

**NEW:**
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'customer.auth' => \App\Http\Middleware\CustomerAuthenticate::class,
        'customer.api.auth' => \App\Http\Middleware\CustomerApiAuthenticate::class,
        'customer.guest' => \App\Http\Middleware\RedirectIfCustomerAuthenticated::class,
    ]);
})
```

**Also Fixed:** `routes/ecommerce.php.php` → `routes/ecommerce.php` (removed duplicate .php)

---

## 13. Controllers (NEW FILES)

### Web AuthController
**File:** `app/Http/Controllers/Customer/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginRequest;
use App\Http\Requests\Customer\RegisterRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('customer.auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::guard('customer')->attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('customer.dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegistrationForm(): View
    {
        return view('customer.auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $customer = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'password' => $request->password,
            'code' => $this->generateCustomerCode(),
            'status' => true,
            'user_id' => 0,
            'points' => 0,
        ]);

        Auth::guard('customer')->login($customer);

        return redirect()->route('customer.dashboard')
            ->with('success', 'Registration successful!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }

    protected function generateCustomerCode(): string
    {
        do {
            $code = 'CUST-'.strtoupper(Str::random(8));
        } while (Customer::where('code', $code)->exists());

        return $code;
    }
}
```

### API AuthController
**File:** `app/Http/Controllers/API/v1/customer/AuthController.php`

```php
<?php

namespace App\Http\Controllers\API\v1\customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginRequest;
use App\Http\Requests\Customer\RegisterRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Traits\ApiResponse;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('customer')->attempt($credentials)) {
            return $this->error('Invalid credentials', 401);
        }

        $customer = Auth::guard('customer')->user();

        if (! $customer->status) {
            Auth::guard('customer')->logout();
            return $this->error('Your account has been deactivated', 403);
        }

        $token = $customer->createToken('Customer Access Token')->accessToken;

        return $this->success([
            'customer' => new CustomerResource($customer),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $customer = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'password' => $request->password,
            'code' => $this->generateCustomerCode(),
            'status' => true,
            'user_id' => 0,
            'points' => 0,
        ]);

        $token = $customer->createToken('Customer Access Token')->accessToken;

        return $this->created([
            'customer' => new CustomerResource($customer),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registration successful');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new CustomerResource($request->user('customer-api'))
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('customer-api')->token()->revoke();
        return $this->success(null, 'Logged out successfully');
    }

    protected function generateCustomerCode(): string
    {
        do {
            $code = 'CUST-'.strtoupper(Str::random(8));
        } while (Customer::where('code', $code)->exists());

        return $code;
    }
}
```

---

## 14. Form Requests (NEW FILES)

### LoginRequest
**File:** `app/Http/Requests/Customer/LoginRequest.php`

```php
<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.',
        ];
    }
}
```

### RegisterRequest
**File:** `app/Http/Requests/Customer/RegisterRequest.php`

```php
<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
```

---

## 15. Routes

### Web Routes (ecommerce.php)
**File:** `routes/ecommerce.php`

```php
Route::prefix('customer')->name('customer.')->group(function () {
    // Guest routes (not logged in)
    Route::middleware('customer.guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.submit');
        Route::get('register', [AuthController::class, 'showRegistrationForm'])->name('register');
        Route::post('register', [AuthController::class, 'register'])->name('register.submit');
    });

    // Authenticated routes
    Route::middleware('customer.auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', function () {
            return view('customer.dashboard');
        })->name('dashboard');
    });
});
```

### API Routes (api.php)
**File:** `routes/api.php`

```php
// Customer E-commerce API
Route::group(['prefix' => 'v1/customer'], function () {
    // Public routes (no authentication required)
    Route::post('/login', [\App\Http\Controllers\API\v1\customer\AuthController::class, 'login']);
    Route::post('/register', [\App\Http\Controllers\API\v1\customer\AuthController::class, 'register']);

    // Protected routes (customer authentication required)
    Route::middleware('auth:customer-api')->group(function () {
        Route::get('/me', [\App\Http\Controllers\API\v1\customer\AuthController::class, 'me']);
        Route::post('/logout', [\App\Http\Controllers\API\v1\customer\AuthController::class, 'logout']);
    });
});
```

---

## 16. CustomerFactory (NEW FILE)

**File:** `database/factories/CustomerFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'code' => 'CUST-'.strtoupper(Str::random(8)),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'zip' => fake()->postcode(),
            'province' => fake()->state(),
            'country' => fake()->country(),
            'password' => 'password',
            'status' => true,
            'user_id' => 0,
            'points' => fake()->randomFloat(2, 0, 1000),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
```

---

## Summary - Customer Authentication

| Category | Count |
|----------|-------|
| Migration | 1 |
| Model Updated | 1 |
| Middleware Created | 3 |
| Controllers Created | 2 |
| Form Requests Created | 2 |
| Views Created | 4 |
| Factory Created | 1 |
| Tests Created | 2 (19 test methods) |
| Config Files Updated | 2 |
| Route Files Updated | 2 |

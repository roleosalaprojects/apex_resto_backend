# Expenses API Specification

## Base URL
```
/api/v1/mobile/expenses
```

## Authentication
All endpoints require authentication via Bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. List Expenses
Get all expenses with filtering and pagination.

**Endpoint:** `GET /`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `category_id` | integer | No | Filter by expense category ID |
| `store_id` | integer | No | Filter by store/branch ID |
| `bank_id` | integer | No | Filter by bank account ID |
| `status` | integer | No | Filter by status (1=Active, 2=Voided) |
| `start_date` | date | No | Filter expenses from this date (YYYY-MM-DD) |
| `end_date` | date | No | Filter expenses until this date (YYYY-MM-DD) |
| `search` | string | No | Search by reference number, payee, description, or receipt number |
| `per_page` | integer | No | Results per page (default: 20) |

**Response:**
```json
{
  "success": true,
  "data": {
    "expenses": [
      {
        "id": 1,
        "reference_number": "EXP-20260125-ABC123",
        "payee": "Electric Company",
        "amount": 5000.00,
        "formatted_amount": "5,000.00",
        "expense_date": "2026-01-25",
        "formatted_date": "Jan 25, 2026",
        "description": "Monthly electricity bill",
        "receipt_number": "INV-12345",
        "status": 1,
        "status_name": "Active",
        "is_active": true,
        "is_voided": false,
        "category": {
          "id": 1,
          "name": "Utilities",
          "description": "Utility expenses"
        },
        "store": {
          "id": 1,
          "name": "Main Branch"
        },
        "bank": {
          "id": 1,
          "account_name": "Operating Account",
          "bank_name": "BDO"
        },
        "created_by": {
          "id": 1,
          "name": "John Doe"
        },
        "created_at": "2026-01-25 10:30:00",
        "updated_at": "2026-01-25 10:30:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 100
    }
  }
}
```

---

### 2. Get Expense Details
Get a specific expense by ID.

**Endpoint:** `GET /{expense_id}`

**Response:**
```json
{
  "success": true,
  "data": {
    "expense": {
      "id": 1,
      "reference_number": "EXP-20260125-ABC123",
      "payee": "Electric Company",
      "amount": 5000.00,
      "formatted_amount": "5,000.00",
      "expense_date": "2026-01-25",
      "formatted_date": "Jan 25, 2026",
      "description": "Monthly electricity bill",
      "receipt_number": "INV-12345",
      "status": 1,
      "status_name": "Active",
      "is_active": true,
      "is_voided": false,
      "category": {
        "id": 1,
        "name": "Utilities",
        "description": "Utility expenses"
      },
      "store": {
        "id": 1,
        "name": "Main Branch"
      },
      "bank": {
        "id": 1,
        "account_name": "Operating Account",
        "bank_name": "BDO"
      },
      "bank_transaction": {
        "id": 15,
        "reference_number": "TXN-20260125-XYZ789"
      },
      "created_by": {
        "id": 1,
        "name": "John Doe"
      },
      "approved_by": null,
      "approved_at": null,
      "created_at": "2026-01-25 10:30:00",
      "updated_at": "2026-01-25 10:30:00"
    }
  }
}
```

---

### 3. Record New Expense
Create a new expense (automatically creates a bank withdrawal transaction).

**Endpoint:** `POST /`

**Request Body:**
```json
{
  "bank_id": 1,
  "expense_category_id": 1,
  "store_id": 1,
  "payee": "Electric Company",
  "amount": 5000.00,
  "expense_date": "2026-01-25",
  "description": "Monthly electricity bill",
  "receipt_number": "INV-12345"
}
```

**Fields:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `bank_id` | integer | Yes | Bank account to withdraw from |
| `expense_category_id` | integer | No | Expense category ID |
| `store_id` | integer | No | Store/branch ID |
| `payee` | string | Yes | Payee/vendor name (max 255 chars) |
| `amount` | decimal | Yes | Amount (must be > 0 and <= bank balance) |
| `expense_date` | date | Yes | Date of expense (YYYY-MM-DD) |
| `description` | string | No | Description/notes (max 1000 chars) |
| `receipt_number` | string | No | Receipt/invoice number (max 100 chars) |

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Expense of 5,000.00 recorded successfully.",
  "data": {
    "expense": {
      "id": 1,
      "reference_number": "EXP-20260125-ABC123",
      "payee": "Electric Company",
      "amount": 5000.00,
      "formatted_amount": "5,000.00",
      "expense_date": "2026-01-25",
      "formatted_date": "Jan 25, 2026",
      "description": "Monthly electricity bill",
      "receipt_number": "INV-12345",
      "status": 1,
      "status_name": "Active",
      "is_active": true,
      "is_voided": false,
      "category": {
        "id": 1,
        "name": "Utilities"
      },
      "store": {
        "id": 1,
        "name": "Main Branch"
      },
      "bank": {
        "id": 1,
        "account_name": "Operating Account",
        "bank_name": "BDO"
      },
      "created_by": {
        "id": 1,
        "name": "John Doe"
      },
      "created_at": "2026-01-25 10:30:00",
      "updated_at": "2026-01-25 10:30:00"
    },
    "bank_new_balance": 95000.00
  }
}
```

**Error Response (Insufficient Balance):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "amount": ["Insufficient bank balance. Available: 3,000.00"]
  }
}
```

---

### 4. Update Expense
Update non-financial fields of an expense. Bank account and amount cannot be changed.

**Endpoint:** `PUT /{expense_id}`

**Request Body:**
```json
{
  "expense_category_id": 2,
  "store_id": 2,
  "payee": "Updated Payee Name",
  "expense_date": "2026-01-24",
  "description": "Updated description",
  "receipt_number": "INV-54321"
}
```

**Fields:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `expense_category_id` | integer | No | Expense category ID |
| `store_id` | integer | No | Store/branch ID |
| `payee` | string | No | Payee/vendor name |
| `expense_date` | date | No | Date of expense |
| `description` | string | No | Description/notes |
| `receipt_number` | string | No | Receipt/invoice number |

**Response:**
```json
{
  "success": true,
  "message": "Expense updated successfully.",
  "data": {
    "expense": { ... }
  }
}
```

**Error Response (Voided Expense):**
```json
{
  "success": false,
  "message": "Cannot update a voided expense."
}
```

---

### 5. Void Expense
Void an expense and reverse the bank transaction (restores funds to the bank account).

**Endpoint:** `POST /{expense_id}/void`

**Response:**
```json
{
  "success": true,
  "message": "Expense voided and funds reversed successfully.",
  "data": {
    "expense": {
      "id": 1,
      "reference_number": "EXP-20260125-ABC123",
      "status": 2,
      "status_name": "Voided",
      "is_active": false,
      "is_voided": true,
      ...
    },
    "bank_new_balance": 100000.00
  }
}
```

**Error Response (Already Voided):**
```json
{
  "success": false,
  "message": "This expense has already been voided."
}
```

---

### 6. Get Expense Categories
Get all active expense categories.

**Endpoint:** `GET /categories`

**Response:**
```json
{
  "success": true,
  "data": {
    "categories": [
      {
        "id": 1,
        "name": "Utilities",
        "description": "Electricity, water, internet, etc.",
        "status": true,
        "expenses_count": 25,
        "created_at": "2026-01-01 00:00:00",
        "updated_at": "2026-01-01 00:00:00"
      },
      {
        "id": 2,
        "name": "Office Supplies",
        "description": "Paper, pens, office materials",
        "status": true,
        "expenses_count": 12,
        "created_at": "2026-01-01 00:00:00",
        "updated_at": "2026-01-01 00:00:00"
      }
    ]
  }
}
```

---

### 7. Get Expense Summary
Get expense dashboard summary with totals by category and store.

**Endpoint:** `GET /summary`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `period` | string | No | Time period filter (default: "this_month") |

**Valid Period Values:**
- `today`
- `yesterday`
- `this_week`
- `last_week`
- `this_month`
- `last_month`
- `this_year`

**Response:**
```json
{
  "success": true,
  "data": {
    "period": "this_month",
    "date_range": {
      "start": "2026-01-01",
      "end": "2026-01-25"
    },
    "summary": {
      "total_amount": 150000.00,
      "formatted_total": "150,000.00",
      "expense_count": 45
    },
    "by_category": [
      {
        "category_id": 1,
        "category_name": "Utilities",
        "total": 50000.00,
        "count": 10
      },
      {
        "category_id": 2,
        "category_name": "Office Supplies",
        "total": 25000.00,
        "count": 15
      },
      {
        "category_id": null,
        "category_name": "Uncategorized",
        "total": 10000.00,
        "count": 5
      }
    ],
    "by_store": [
      {
        "store_id": 1,
        "store_name": "Main Branch",
        "total": 80000.00,
        "count": 30
      },
      {
        "store_id": null,
        "store_name": "All Stores",
        "total": 20000.00,
        "count": 10
      }
    ],
    "recent_expenses": [
      {
        "id": 45,
        "reference_number": "EXP-20260125-XYZ789",
        "payee": "Recent Vendor",
        "amount": 1500.00,
        "formatted_amount": "1,500.00",
        "expense_date": "2026-01-25",
        "formatted_date": "Jan 25, 2026",
        ...
      }
    ]
  }
}
```

---

## TypeScript Interfaces

```typescript
interface ExpenseCategory {
  id: number;
  name: string;
  description: string | null;
  status: boolean;
  expenses_count?: number;
  created_at: string;
  updated_at: string;
}

interface Expense {
  id: number;
  reference_number: string;
  payee: string;
  amount: number;
  formatted_amount: string;
  expense_date: string;
  formatted_date: string;
  description: string | null;
  receipt_number: string | null;
  status: number;
  status_name: string;
  is_active: boolean;
  is_voided: boolean;
  category: ExpenseCategory | null;
  store: { id: number; name: string } | null;
  bank: { id: number; account_name: string; bank_name: string };
  bank_transaction?: { id: number; reference_number: string };
  created_by: { id: number; name: string };
  approved_by: { id: number; name: string } | null;
  approved_at: string | null;
  created_at: string;
  updated_at: string;
}

interface ExpenseListResponse {
  success: boolean;
  data: {
    expenses: Expense[];
    pagination: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  };
}

interface ExpenseDetailResponse {
  success: boolean;
  data: {
    expense: Expense;
  };
}

interface CreateExpenseRequest {
  bank_id: number;
  expense_category_id?: number;
  store_id?: number;
  payee: string;
  amount: number;
  expense_date: string;
  description?: string;
  receipt_number?: string;
}

interface CreateExpenseResponse {
  success: boolean;
  message: string;
  data: {
    expense: Expense;
    bank_new_balance: number;
  };
}

interface UpdateExpenseRequest {
  expense_category_id?: number;
  store_id?: number;
  payee?: string;
  expense_date?: string;
  description?: string;
  receipt_number?: string;
}

interface VoidExpenseResponse {
  success: boolean;
  message: string;
  data: {
    expense: Expense;
    bank_new_balance: number;
  };
}

interface ExpenseCategoriesResponse {
  success: boolean;
  data: {
    categories: ExpenseCategory[];
  };
}

interface ExpenseSummary {
  total_amount: number;
  formatted_total: string;
  expense_count: number;
}

interface CategorySummary {
  category_id: number | null;
  category_name: string;
  total: number;
  count: number;
}

interface StoreSummary {
  store_id: number | null;
  store_name: string;
  total: number;
  count: number;
}

interface ExpenseSummaryResponse {
  success: boolean;
  data: {
    period: string;
    date_range: {
      start: string;
      end: string;
    };
    summary: ExpenseSummary;
    by_category: CategorySummary[];
    by_store: StoreSummary[];
    recent_expenses: Expense[];
  };
}
```

---

## Status Codes

| Status | Description |
|--------|-------------|
| 1 | Active - Normal expense |
| 2 | Voided - Expense has been cancelled, funds reversed |

---

## Error Responses

All error responses follow this format:

```json
{
  "success": false,
  "message": "Error description"
}
```

**Validation Errors:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

---

## Usage Examples

### Record a New Expense (React Native/TypeScript)

```typescript
const recordExpense = async (data: CreateExpenseRequest): Promise<CreateExpenseResponse> => {
  const response = await fetch(`${API_BASE_URL}/expenses`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(data),
  });

  return response.json();
};

// Usage
const result = await recordExpense({
  bank_id: 1,
  expense_category_id: 2,
  store_id: 1,
  payee: 'Office Depot',
  amount: 1500.00,
  expense_date: '2026-01-25',
  description: 'Office supplies purchase',
  receipt_number: 'OD-12345',
});

if (result.success) {
  console.log('Expense recorded:', result.data.expense.reference_number);
  console.log('New bank balance:', result.data.bank_new_balance);
}
```

### Fetch Expenses with Filters

```typescript
const fetchExpenses = async (filters: {
  category_id?: number;
  store_id?: number;
  start_date?: string;
  end_date?: string;
  search?: string;
  page?: number;
}): Promise<ExpenseListResponse> => {
  const params = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined) {
      params.append(key, String(value));
    }
  });

  const response = await fetch(`${API_BASE_URL}/expenses?${params}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });

  return response.json();
};

// Usage - Get this month's utility expenses
const expenses = await fetchExpenses({
  category_id: 1, // Utilities
  start_date: '2026-01-01',
  end_date: '2026-01-31',
});
```

### Void an Expense

```typescript
const voidExpense = async (expenseId: number): Promise<VoidExpenseResponse> => {
  const response = await fetch(`${API_BASE_URL}/expenses/${expenseId}/void`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });

  return response.json();
};

// Usage
const result = await voidExpense(123);
if (result.success) {
  Alert.alert('Success', result.message);
  // Refresh expense list and bank balance
}
```

# Banking API Specification

## Base URL
```
http://localhost/api/v1/mobile
```

## Authentication
All endpoints require Bearer token authentication.

```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. Get All Banks/Accounts

**GET** `/banks`

List all bank accounts with optional filtering.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `account_type` | integer | No | Filter by account type (0-4) |
| `ewallets_only` | boolean | No | Set to `true` to get only e-wallets |
| `search` | string | No | Search by bank name, account name, or account number |

#### Response
```json
{
  "success": true,
  "data": {
    "banks": [
      {
        "id": 1,
        "bank_name": "BPI",
        "account_number": "1234567890",
        "account_name": "APEX Store",
        "account_type": 0,
        "account_type_name": "Savings",
        "opening_balance": 10000.00,
        "balance": 15500.50,
        "description": "Main operating account",
        "created_at": "2024-01-15T08:00:00+00:00",
        "updated_at": "2024-01-20T10:30:00+00:00"
      }
    ],
    "account_types": {
      "savings": 0,
      "checking": 1,
      "credit": 2,
      "passbook": 3,
      "ewallet": 4
    }
  }
}
```

---

### 2. Get Bank Details

**GET** `/banks/{bank_id}`

Get a specific bank account with recent transactions.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `bank_id` | integer | Yes | The bank account ID |

#### Response
```json
{
  "success": true,
  "data": {
    "bank": {
      "id": 1,
      "bank_name": "BPI",
      "account_number": "1234567890",
      "account_name": "APEX Store",
      "account_type": 0,
      "account_type_name": "Savings",
      "opening_balance": 10000.00,
      "balance": 15500.50,
      "description": "Main operating account",
      "total_deposits": 8000.00,
      "total_withdrawals": 2500.00,
      "recent_transactions": [
        {
          "id": 45,
          "reference_number": "TXN-20240120-ABC123",
          "bank_id": 1,
          "transfer_to_bank_id": null,
          "type": 1,
          "type_name": "Deposit",
          "is_debit": false,
          "is_credit": true,
          "amount": 1000.00,
          "balance_before": 14500.50,
          "balance_after": 15500.50,
          "description": "Cash Sales Deposit",
          "payee": "Daily Sales",
          "transaction_date": "2024-01-20",
          "created_by": {
            "id": 1,
            "name": "Admin User"
          },
          "created_at": "2024-01-20T10:30:00+00:00",
          "updated_at": "2024-01-20T10:30:00+00:00"
        }
      ],
      "created_at": "2024-01-15T08:00:00+00:00",
      "updated_at": "2024-01-20T10:30:00+00:00"
    }
  }
}
```

---

### 3. Get Bank Transactions

**GET** `/banks/{bank_id}/transactions`

Get paginated transactions for a specific bank with filtering options.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `bank_id` | integer | Yes | The bank account ID |

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | integer | No | Filter by transaction type (1=Deposit, 2=Withdrawal, 3=Transfer Out, 4=Transfer In) |
| `start_date` | date | No | Filter transactions from this date (YYYY-MM-DD) |
| `end_date` | date | No | Filter transactions until this date (YYYY-MM-DD) |
| `search` | string | No | Search by reference number, description, or payee |
| `per_page` | integer | No | Number of items per page (default: 20) |
| `page` | integer | No | Page number for pagination |

#### Response
```json
{
  "success": true,
  "data": {
    "transactions": [
      {
        "id": 45,
        "reference_number": "TXN-20240120-ABC123",
        "bank_id": 1,
        "transfer_to_bank_id": null,
        "type": 1,
        "type_name": "Deposit",
        "is_debit": false,
        "is_credit": true,
        "amount": 1000.00,
        "balance_before": 14500.50,
        "balance_after": 15500.50,
        "description": "Cash Sales Deposit",
        "payee": "Daily Sales",
        "transaction_date": "2024-01-20",
        "created_by": {
          "id": 1,
          "name": "Admin User"
        },
        "created_at": "2024-01-20T10:30:00+00:00",
        "updated_at": "2024-01-20T10:30:00+00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 95
    },
    "transaction_types": {
      "deposit": 1,
      "withdrawal": 2,
      "transfer_out": 3,
      "transfer_in": 4
    }
  }
}
```

---

### 4. Record Deposit

**POST** `/banks/{bank_id}/deposit`

Record a deposit transaction for a bank account.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `bank_id` | integer | Yes | The bank account ID |

#### Request Body
```json
{
  "amount": 1000.00,
  "payee": "Daily Sales",
  "description": "Cash sales deposit for January 20",
  "transaction_date": "2024-01-20"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `amount` | number | Yes | The deposit amount (min: 0.01) |
| `payee` | string | No | Source of the deposit (max: 255 chars) |
| `description` | string | No | Transaction description (max: 500 chars) |
| `transaction_date` | date | Yes | Date of transaction (YYYY-MM-DD, cannot be future) |

#### Response (201 Created)
```json
{
  "success": true,
  "message": "Deposit of 1,000.00 recorded successfully.",
  "data": {
    "transaction": {
      "id": 46,
      "reference_number": "TXN-20240120-DEF456",
      "bank_id": 1,
      "transfer_to_bank_id": null,
      "type": 1,
      "type_name": "Deposit",
      "is_debit": false,
      "is_credit": true,
      "amount": 1000.00,
      "balance_before": 15500.50,
      "balance_after": 16500.50,
      "description": "Cash sales deposit for January 20",
      "payee": "Daily Sales",
      "transaction_date": "2024-01-20",
      "created_by": {
        "id": 1,
        "name": "Admin User"
      },
      "created_at": "2024-01-20T11:00:00+00:00",
      "updated_at": "2024-01-20T11:00:00+00:00"
    },
    "new_balance": 16500.50
  }
}
```

---

### 5. Record Withdrawal

**POST** `/banks/{bank_id}/withdrawal`

Record a withdrawal transaction from a bank account.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `bank_id` | integer | Yes | The bank account ID |

#### Request Body
```json
{
  "amount": 500.00,
  "payee": "Supplier ABC",
  "description": "Payment for inventory purchase",
  "transaction_date": "2024-01-20"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `amount` | number | Yes | The withdrawal amount (min: 0.01, must not exceed balance) |
| `payee` | string | No | Recipient of the withdrawal (max: 255 chars) |
| `description` | string | No | Transaction description (max: 500 chars) |
| `transaction_date` | date | Yes | Date of transaction (YYYY-MM-DD, cannot be future) |

#### Response (201 Created)
```json
{
  "success": true,
  "message": "Withdrawal of 500.00 recorded successfully.",
  "data": {
    "transaction": {
      "id": 47,
      "reference_number": "TXN-20240120-GHI789",
      "bank_id": 1,
      "transfer_to_bank_id": null,
      "type": 2,
      "type_name": "Withdrawal",
      "is_debit": true,
      "is_credit": false,
      "amount": 500.00,
      "balance_before": 16500.50,
      "balance_after": 16000.50,
      "description": "Payment for inventory purchase",
      "payee": "Supplier ABC",
      "transaction_date": "2024-01-20",
      "created_by": {
        "id": 1,
        "name": "Admin User"
      },
      "created_at": "2024-01-20T11:30:00+00:00",
      "updated_at": "2024-01-20T11:30:00+00:00"
    },
    "new_balance": 16000.50
  }
}
```

#### Error Response (400 Bad Request - Insufficient Balance)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "amount": ["Insufficient balance. Available: 16,000.50"]
  }
}
```

---

### 6. Transfer Funds

**POST** `/banks/{bank_id}/transfer`

Transfer funds between bank accounts.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `bank_id` | integer | Yes | The source bank account ID |

#### Request Body
```json
{
  "transfer_to_bank_id": 2,
  "amount": 2000.00,
  "description": "Transfer to petty cash fund",
  "transaction_date": "2024-01-20"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `transfer_to_bank_id` | integer | Yes | The destination bank account ID |
| `amount` | number | Yes | The transfer amount (min: 0.01, must not exceed balance) |
| `description` | string | No | Transaction description (max: 500 chars) |
| `transaction_date` | date | Yes | Date of transaction (YYYY-MM-DD, cannot be future) |

#### Response (201 Created)
```json
{
  "success": true,
  "message": "Transfer of 2,000.00 to Petty Cash completed successfully.",
  "data": {
    "source_transaction": {
      "id": 48,
      "reference_number": "TXN-20240120-JKL012",
      "bank_id": 1,
      "transfer_to_bank_id": 2,
      "type": 3,
      "type_name": "Transfer Out",
      "is_debit": true,
      "is_credit": false,
      "amount": 2000.00,
      "balance_before": 16000.50,
      "balance_after": 14000.50,
      "description": "Transfer to petty cash fund",
      "payee": null,
      "transaction_date": "2024-01-20",
      "transfer_to_bank": {
        "id": 2,
        "bank_name": "Cash on Hand",
        "account_name": "Petty Cash"
      },
      "created_by": {
        "id": 1,
        "name": "Admin User"
      },
      "created_at": "2024-01-20T12:00:00+00:00",
      "updated_at": "2024-01-20T12:00:00+00:00"
    },
    "destination_transaction": {
      "id": 49,
      "reference_number": "TXN-20240120-JKL012-IN",
      "bank_id": 2,
      "transfer_to_bank_id": 1,
      "type": 4,
      "type_name": "Transfer In",
      "is_debit": false,
      "is_credit": true,
      "amount": 2000.00,
      "balance_before": 5000.00,
      "balance_after": 7000.00,
      "description": "Transfer to petty cash fund",
      "payee": null,
      "transaction_date": "2024-01-20",
      "transfer_to_bank": {
        "id": 1,
        "bank_name": "BPI",
        "account_name": "APEX Store"
      },
      "created_by": {
        "id": 1,
        "name": "Admin User"
      },
      "created_at": "2024-01-20T12:00:00+00:00",
      "updated_at": "2024-01-20T12:00:00+00:00"
    },
    "source_new_balance": 14000.50,
    "destination_new_balance": 7000.00
  }
}
```

---

### 7. Get Banking Summary

**GET** `/banks/summary`

Get a dashboard summary of all banking operations.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `period` | string | No | Time period filter. Options: `today`, `yesterday`, `this_week`, `last_week`, `this_month` (default), `last_month`, `this_year` |

#### Response
```json
{
  "success": true,
  "data": {
    "total_balance": 85000.50,
    "balances_by_type": {
      "0": {
        "total_balance": 50000.00,
        "account_count": 2
      },
      "4": {
        "total_balance": 35000.50,
        "account_count": 3
      }
    },
    "period": "this_month",
    "date_range": {
      "start": "2024-01-01",
      "end": "2024-01-20"
    },
    "transaction_summary": {
      "total_deposits": 25000.00,
      "total_withdrawals": 12000.00,
      "net_flow": 13000.00,
      "deposit_count": 45,
      "withdrawal_count": 23,
      "transfer_count": 8
    },
    "recent_transactions": [
      {
        "id": 49,
        "reference_number": "TXN-20240120-JKL012-IN",
        "type": 4,
        "type_name": "Transfer In",
        "amount": 2000.00,
        "transaction_date": "2024-01-20",
        "bank": {
          "id": 2,
          "bank_name": "Cash on Hand",
          "account_name": "Petty Cash"
        },
        "created_by": {
          "id": 1,
          "name": "Admin User"
        }
      }
    ]
  }
}
```

---

## Constants Reference

### Account Types
| Value | Name | Description |
|-------|------|-------------|
| 0 | Savings | Savings bank account |
| 1 | Checking | Checking/Current account |
| 2 | Credit | Credit card account |
| 3 | Passbook | Passbook savings account |
| 4 | E-Wallet | Electronic wallet (GCash, Maya, etc.) |

### Transaction Types
| Value | Name | Description |
|-------|------|-------------|
| 1 | Deposit | Money coming into the account |
| 2 | Withdrawal | Money going out of the account |
| 3 | Transfer Out | Money transferred to another account (debit) |
| 4 | Transfer In | Money received from another account (credit) |

---

## Error Responses

### Validation Error (422 Unprocessable Entity)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "amount": ["The amount field is required."],
    "transaction_date": ["The transaction date field is required."]
  }
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### Unauthorized (401)
```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "An error occurred while processing your request."
}
```

---

## TypeScript Interfaces

For frontend type safety, you can use these TypeScript interfaces:

```typescript
// Account Types
export enum AccountType {
  SAVINGS = 0,
  CHECKING = 1,
  CREDIT = 2,
  PASSBOOK = 3,
  EWALLET = 4,
}

// Transaction Types
export enum TransactionType {
  DEPOSIT = 1,
  WITHDRAWAL = 2,
  TRANSFER_OUT = 3,
  TRANSFER_IN = 4,
}

export interface Bank {
  id: number;
  bank_name: string;
  account_number: string;
  account_name: string;
  account_type: AccountType;
  account_type_name: string;
  opening_balance: number;
  balance: number;
  description: string | null;
  total_deposits?: number;
  total_withdrawals?: number;
  recent_transactions?: BankTransaction[];
  created_at: string;
  updated_at: string;
}

export interface BankTransaction {
  id: number;
  reference_number: string;
  bank_id: number;
  transfer_to_bank_id: number | null;
  type: TransactionType;
  type_name: string;
  is_debit: boolean;
  is_credit: boolean;
  amount: number;
  balance_before: number;
  balance_after: number;
  description: string | null;
  payee: string | null;
  transaction_date: string;
  bank?: Bank;
  transfer_to_bank?: Bank;
  created_by?: {
    id: number;
    name: string;
  };
  created_at: string;
  updated_at: string;
}

export interface DepositRequest {
  amount: number;
  payee?: string;
  description?: string;
  transaction_date: string;
}

export interface WithdrawalRequest {
  amount: number;
  payee?: string;
  description?: string;
  transaction_date: string;
}

export interface TransferRequest {
  transfer_to_bank_id: number;
  amount: number;
  description?: string;
  transaction_date: string;
}

export interface BankingSummary {
  total_balance: number;
  balances_by_type: Record<string, {
    total_balance: number;
    account_count: number;
  }>;
  period: string;
  date_range: {
    start: string;
    end: string;
  };
  transaction_summary: {
    total_deposits: number;
    total_withdrawals: number;
    net_flow: number;
    deposit_count: number;
    withdrawal_count: number;
    transfer_count: number;
  };
  recent_transactions: BankTransaction[];
}

export interface Pagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// API Response wrapper
export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
}
```

---

## Usage Examples

### React/React Native Example

```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost/api/v1/mobile',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Set auth token
api.defaults.headers.common['Authorization'] = `Bearer ${token}`;

// Get all banks
const getBanks = async () => {
  const response = await api.get('/banks');
  return response.data.data.banks;
};

// Get bank details
const getBankDetails = async (bankId: number) => {
  const response = await api.get(`/banks/${bankId}`);
  return response.data.data.bank;
};

// Get transactions with filters
const getTransactions = async (bankId: number, filters: {
  type?: number;
  start_date?: string;
  end_date?: string;
  search?: string;
  per_page?: number;
  page?: number;
}) => {
  const response = await api.get(`/banks/${bankId}/transactions`, { params: filters });
  return response.data.data;
};

// Record deposit
const recordDeposit = async (bankId: number, data: DepositRequest) => {
  const response = await api.post(`/banks/${bankId}/deposit`, data);
  return response.data;
};

// Record withdrawal
const recordWithdrawal = async (bankId: number, data: WithdrawalRequest) => {
  const response = await api.post(`/banks/${bankId}/withdrawal`, data);
  return response.data;
};

// Transfer funds
const transferFunds = async (sourceBankId: number, data: TransferRequest) => {
  const response = await api.post(`/banks/${sourceBankId}/transfer`, data);
  return response.data;
};

// Get banking summary
const getBankingSummary = async (period?: string) => {
  const response = await api.get('/banks/summary', { params: { period } });
  return response.data.data;
};
```

---

## Notes

1. All monetary values are returned as floats with 2 decimal precision.
2. Dates are returned in ISO 8601 format for timestamps and `YYYY-MM-DD` for date-only fields.
3. Transaction dates cannot be set in the future.
4. Withdrawals and transfers validate against the current account balance.
5. Transfers create two linked transactions (one debit, one credit) with related reference numbers.
6. The `summary` endpoint provides a dashboard-ready overview of all banking activity.

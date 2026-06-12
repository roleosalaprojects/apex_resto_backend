# Apex Dashboard - Application Specification

## Overview

Apex Dashboard is a mobile/tablet application for business owners and managers to monitor and manage their retail operations in real-time. It connects to the Apex Backend API to provide insights, analytics, and management capabilities.

**Target Platforms:** iOS, Android, Web (responsive)
**Tech Stack Recommendation:** Flutter or React Native for cross-platform

---

## Authentication

### Login
- **Endpoint:** `POST /api/v1/mobile/login`
- **Payload:** `{ "email": "string", "password": "string" }`
- **Response:** `{ "token": "string", "user": {...} }`
- **Storage:** Securely store token in device keychain/secure storage

### Get Current User
- **Endpoint:** `GET /api/v1/mobile/getUser`
- **Headers:** `Authorization: Bearer {token}`

### Logout
- **Endpoint:** `POST /api/v1/mobile/logout`

---

## Main Navigation Structure

```
├── Dashboard (Home)
├── Sales
│   ├── Live Ticker
│   ├── Sales Summary
│   └── Sales by Item
├── Products
│   ├── Product List
│   ├── Product Performance
│   └── Price History
├── Inventory
│   ├── Stock Levels
│   ├── Low Stock Alerts
│   ├── Adjustments
│   ├── Transfers
│   └── Inventory Counts
├── Customers
│   ├── Top Customers
│   ├── Customer Trends
│   └── Loyalty Points
├── Purchases
│   ├── Purchase Orders
│   ├── Pending Approvals
│   └── Receiving
├── Reports
│   ├── Category Report
│   ├── Supplier Report
│   ├── Refunds Report
│   └── Inventory Report
├── Finance
│   ├── Bank Accounts
│   ├── Expenses
│   └── Banking Transactions
├── Staff
│   ├── Attendance
│   ├── Staff Leaderboard
│   └── Schedules
├── AI Insights
│   ├── Sales Forecast
│   ├── Reorder Suggestions
│   └── Demand Patterns
└── Settings
    ├── Profile
    ├── Notifications
    └── Store Selection
```

---

## Screen Specifications

### 1. Dashboard (Home Screen)

The main landing screen showing key metrics at a glance.

#### Components:

**A. Revenue Summary Card**
- Today's sales vs yesterday (with % change)
- This week vs last week
- This month vs last month
- **Endpoint:** `GET /api/v1/mobile/dashboard/revenue-comparison`

**B. Live Sales Ticker**
- Real-time scrolling feed of recent transactions
- Shows: time, amount, store, cashier
- Tap to view receipt details
- **Endpoint:** `GET /api/v1/mobile/dashboard/sales-ticker?limit=20`
- **Refresh:** Auto-refresh every 30 seconds or pull-to-refresh

**C. Top Products Widget**
- Top 5 selling products today
- Shows: product name, qty sold, revenue
- **Endpoint:** `GET /api/v1/mobile/dashboard/top-products?period=today&limit=5`

**D. Staff Leaderboard Widget**
- Top 3 performers today
- Shows: name, transactions, total sales
- **Endpoint:** `GET /api/v1/mobile/dashboard/staff-leaderboard?period=today&limit=3`

**E. Quick Actions**
- Low Stock Alert badge (count)
- Pending Approvals badge (count)
- New Orders badge (count)

**F. Sales Chart**
- Line/bar chart showing hourly sales for today
- **Endpoint:** `GET /api/v1/mobile/sales-summary?start_date=today&end_date=today`

---

### 2. Sales Screens

#### 2.1 Sales Summary
- Date range picker (presets: today, yesterday, this week, this month, custom)
- Store filter (multi-select)
- **Metrics Display:**
  - Gross Sales
  - Refunds
  - Net Sales (Profit)
  - Transaction Count
- **Chart:** Line chart showing trend over selected period
- **Endpoint:** `GET /api/v1/mobile/sales-summary`

#### 2.2 Sales by Item
- Sortable list of items by revenue/quantity
- Search/filter functionality
- Tap item for detailed performance view
- **Endpoint:** `GET /api/v1/mobile/sales-by-item`

#### 2.3 Product Performance Detail
- Product info header (name, SKU, category)
- Performance metrics over time
- Chart showing sales trend
- **Endpoint:** `GET /api/v1/mobile/products/{itemId}/performance`

---

### 3. Inventory Screens

#### 3.1 Stock Levels
- List of all products with current stock
- Filter by store, category
- Sort by stock level (low to high)
- Color coding: red (critical), yellow (low), green (ok)
- **Endpoint:** `GET /api/v1/mobile/reports/inventory`

#### 3.2 Low Stock Alerts
- Filtered view of items below minimum threshold
- Quick action: Create purchase order
- **Endpoint:** `GET /api/v1/mobile/inventory/low-stock`

#### 3.3 Stock Adjustments
- List of adjustments with filters
- Create new adjustment
- View adjustment details
- **Endpoints:**
  - `GET /api/v1/mobile/inventory/adjustments`
  - `POST /api/v1/mobile/inventory/adjustments`
  - `GET /api/v1/mobile/inventory/adjustment-reasons`

#### 3.4 Stock Transfers
- List of transfers (incoming/outgoing/all)
- Status filters: pending, in_transit, received, cancelled
- Create new transfer
- Update transfer status
- **Endpoints:**
  - `GET /api/v1/mobile/inventory/transfers`
  - `POST /api/v1/mobile/inventory/transfers`
  - `PATCH /api/v1/mobile/inventory/transfers/{id}`

#### 3.5 Inventory Counts
- List of inventory count sessions
- Start new count
- Edit count (scan/enter quantities)
- Finalize count with variance report
- **Endpoints:**
  - `GET /api/v1/mobile/inventory/counts`
  - `POST /api/v1/mobile/inventory/counts`
  - `PATCH /api/v1/mobile/inventory/counts/{id}/items`
  - `POST /api/v1/mobile/inventory/counts/{id}/finalize`

---

### 4. Customer Screens

#### 4.1 Top Customers
- Ranked list by total spending
- Filter by date range, store
- Shows: name, transactions, total spent, last purchase
- Tap for customer details
- **Endpoint:** `GET /api/v1/mobile/customers/analytics/top`

#### 4.2 Customer Trends
- Chart showing new vs returning customers
- Customer retention rate
- Active customers over time
- **Endpoint:** `GET /api/v1/mobile/customers/analytics/trends`

#### 4.3 Loyalty Points
- Points summary dashboard
- Points history list (earned, redeemed, expired)
- Top point holders
- **Endpoints:**
  - `GET /api/v1/mobile/customers/analytics/points-summary`
  - `GET /api/v1/mobile/customers/analytics/points-history`

---

### 5. Purchase Order Screens

#### 5.1 Purchase Orders List
- Filter by status: draft, pending_approval, approved, rejected, received
- Sort by date, supplier, total
- **Endpoint:** `GET /api/v1/mobile/purchases`

#### 5.2 Pending Approvals
- Badge showing count on navigation
- List of POs awaiting approval
- Quick approve/reject actions
- **Endpoints:**
  - `GET /api/v1/mobile/purchases/pending-approvals`
  - `GET /api/v1/mobile/purchases/pending-approvals/count`
  - `POST /api/v1/mobile/purchases/{id}/approve`
  - `POST /api/v1/mobile/purchases/{id}/reject`

#### 5.3 PO Detail Screen
- Header: PO#, supplier, date, status
- Line items list with quantities and prices
- Total summary
- Actions: Submit, Approve, Reject, Receive, Pay
- Payment history
- **Endpoints:**
  - `GET /api/v1/mobile/purchases/{id}`
  - `POST /api/v1/mobile/purchases/{id}/receive`
  - `POST /api/v1/mobile/purchases/{id}/pay`

#### 5.4 Create/Edit PO
- Supplier selection
- Add items (search or scan)
- Quantity and price entry
- Notes field
- **Endpoints:**
  - `POST /api/v1/mobile/purchases`
  - `PUT /api/v1/mobile/purchases/{id}`

---

### 6. Finance Screens

#### 6.1 Bank Accounts Overview
- List of all accounts with balances
- Summary totals
- **Endpoint:** `GET /api/v1/mobile/banks/summary`

#### 6.2 Account Detail
- Account info and current balance
- Transaction history (filterable)
- Quick actions: Deposit, Withdraw, Transfer
- **Endpoints:**
  - `GET /api/v1/mobile/banks/{id}`
  - `GET /api/v1/mobile/banks/{id}/transactions`
  - `POST /api/v1/mobile/banks/{id}/deposit`
  - `POST /api/v1/mobile/banks/{id}/withdrawal`
  - `POST /api/v1/mobile/banks/{id}/transfer`

#### 6.3 Expenses
- List of expenses with filters
- Category breakdown chart
- Add new expense
- Void expense
- **Endpoints:**
  - `GET /api/v1/mobile/expenses`
  - `GET /api/v1/mobile/expenses/summary`
  - `GET /api/v1/mobile/expenses/categories`
  - `POST /api/v1/mobile/expenses`
  - `POST /api/v1/mobile/expenses/{id}/void`

---

### 7. Staff Screens

#### 7.1 Staff Leaderboard
- Full leaderboard with performance metrics
- Filter by period, store
- Shows: rank, name, role, transactions, sales, profit, avg transaction
- **Endpoint:** `GET /api/v1/mobile/dashboard/staff-leaderboard`

#### 7.2 Attendance
- Calendar view of attendance
- List view with filters
- Summary stats (present, late, absent)
- Add/edit attendance record
- **Endpoints:**
  - `GET /api/v1/mobile/attendance`
  - `GET /api/v1/mobile/attendance/summary`
  - `POST /api/v1/mobile/attendance`
  - `PUT /api/v1/mobile/attendance/{id}`

#### 7.3 Employee Schedules
- Weekly schedule view
- Edit employee schedule
- **Endpoints:**
  - `GET /api/v1/mobile/employees/{userId}/schedules`
  - `PUT /api/v1/mobile/employees/{userId}/schedules`

---

### 8. AI Insights Screens

#### 8.1 Sales Forecast
- Predicted sales for upcoming days
- Confidence indicators
- Chart with actual vs predicted
- **Endpoint:** `GET /api/v1/mobile/forecast/daily-sales`

#### 8.2 Reorder Suggestions
- AI-generated list of items to reorder
- Shows: item, current stock, suggested qty, reason
- Acknowledge/dismiss suggestions
- Quick action: Create PO from suggestions
- **Endpoints:**
  - `GET /api/v1/mobile/forecast/reorder-suggestions`
  - `POST /api/v1/mobile/forecast/reorder-suggestions/{id}/acknowledge`

#### 8.3 Demand Patterns
- Sales pattern analysis
- Seasonal trends
- Day-of-week patterns
- **Endpoint:** `GET /api/v1/mobile/forecast/patterns`

#### 8.4 Item Demand Forecast
- Specific item demand prediction
- Historical vs forecasted chart
- **Endpoint:** `GET /api/v1/mobile/forecast/items/{itemId}/demand`

---

### 9. Reports Screens

All reports support:
- Date range selection
- Store filtering
- Export to PDF/CSV (if supported)

#### Available Reports:
| Report | Endpoint |
|--------|----------|
| Category Sales | `GET /api/v1/mobile/reports/categories` |
| Category Items | `GET /api/v1/mobile/reports/categories/{id}/items` |
| Supplier Report | `GET /api/v1/mobile/reports/suppliers` |
| Refunds Report | `GET /api/v1/mobile/reports/refunds` |
| Refunds Summary | `GET /api/v1/mobile/reports/refunds/summary` |
| Inventory Report | `GET /api/v1/mobile/reports/inventory` |
| Low Stock Report | `GET /api/v1/mobile/reports/inventory/low-stock` |

---

### 10. Higher Access Requests (Notifications)

When POS requests elevated permissions:
- Push notification to dashboard users
- List of pending requests
- Approve/deny with optional PIN entry
- **Endpoints:**
  - `GET /api/v1/auth/higher-access/pending`
  - `POST /api/v1/auth/higher-access/respond`

---

## UI/UX Guidelines

### Design Principles
1. **Mobile-first:** Optimized for one-hand operation
2. **Data density:** Show key metrics without overwhelming
3. **Quick actions:** Common tasks accessible in 2-3 taps
4. **Real-time feel:** Auto-refresh and pull-to-refresh everywhere
5. **Offline support:** Cache critical data for offline viewing

### Color Scheme
- Primary: Brand color (configurable)
- Success/Positive: Green (#50CD89)
- Warning: Orange (#FFC700)
- Danger/Negative: Red (#F1416C)
- Neutral: Gray scale

### Typography
- Headers: Bold, larger size
- Metrics: Extra bold, prominent
- Body: Regular weight
- Captions: Smaller, muted color

### Charts
- Use consistent color palette
- Support dark/light mode
- Touch-friendly (tap for details)
- Animations for data updates

---

## Push Notifications

### Notification Types
| Type | Trigger | Priority |
|------|---------|----------|
| Higher Access Request | POS requests elevated permission | High |
| Low Stock Alert | Item falls below threshold | Medium |
| PO Approval Needed | New PO submitted for approval | Medium |
| Large Sale | Transaction above threshold | Low |
| Daily Summary | End of business day | Low |

### Implementation
- Firebase Cloud Messaging (FCM) for Android
- Apple Push Notification Service (APNS) for iOS
- Backend sends via Laravel notification channels

---

## Offline Support

### Cached Data
- Dashboard summary (last fetched)
- Product list
- Customer list
- Recent transactions (last 100)

### Sync Strategy
- Background sync when online
- Queue actions taken offline
- Conflict resolution: server wins

---

## Security Requirements

1. **Token Storage:** Use secure storage (Keychain/Keystore)
2. **SSL Pinning:** Pin API certificates
3. **Biometric Auth:** Optional fingerprint/face unlock
4. **Session Timeout:** Auto-logout after inactivity
5. **Data Encryption:** Encrypt cached data at rest

---

## Performance Requirements

| Metric | Target |
|--------|--------|
| App Launch | < 2 seconds |
| Screen Load | < 1 second |
| API Response | < 500ms (95th percentile) |
| Offline Access | Immediate from cache |

---

## Error Handling

### API Errors
- 401: Redirect to login
- 403: Show permission denied message
- 404: Show "not found" state
- 500: Show retry option with error message

### Network Errors
- Show offline indicator
- Use cached data where available
- Queue actions for retry

---

## Analytics & Tracking

Track user behavior for improvement:
- Screen views
- Feature usage
- Error rates
- Performance metrics

Recommended: Firebase Analytics or Mixpanel

---

## Testing Requirements

1. **Unit Tests:** Business logic, data transformations
2. **Widget Tests:** UI components
3. **Integration Tests:** API communication
4. **E2E Tests:** Critical user flows

---

## Release Phases

### Phase 1 - Core Dashboard
- Authentication
- Dashboard home screen
- Sales summary
- Basic reports

### Phase 2 - Inventory & Purchasing
- Stock levels & alerts
- Stock adjustments & transfers
- Purchase orders & approvals

### Phase 3 - Advanced Features
- Customer analytics
- AI insights & forecasting
- Staff management

### Phase 4 - Polish
- Offline support
- Push notifications
- Biometric auth
- Performance optimization

---

## API Base URL

```
Production: https://api.apex-pos.com/api/v1/mobile
Staging: https://staging-api.apex-pos.com/api/v1/mobile
Development: http://localhost/api/v1/mobile
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | TBD | Initial release |

---

## Contact

For API questions or issues, contact the backend team.

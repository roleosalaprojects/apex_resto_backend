# Apex Inventory Count - Application Specification

A standalone mobile-first application for physical inventory counting that syncs with Apex Backend.

---

## Overview

### Purpose
Apex Inventory Count is a dedicated app for conducting physical inventory counts in retail/wholesale environments. Staff walk around the store/warehouse with a mobile device, scan or search products, enter actual quantities, and sync the results back to Apex Backend to update official stock levels.

### Why Standalone?
- **Offline-first** — Warehouses often have poor connectivity; counts must work without internet
- **Focused UX** — Single-purpose app optimized for fast counting, no distractions
- **Multiple counters** — Several staff can count different areas simultaneously
- **Device flexibility** — Works on any smartphone/tablet, no special hardware needed

### Key Workflow
```
┌─────────────────────────────────────────────────────────────────────────┐
│                         INVENTORY COUNT WORKFLOW                         │
└─────────────────────────────────────────────────────────────────────────┘

  APEX BACKEND                    APEX INVENTORY COUNT
  ────────────                    ────────────────────
       │                                  │
       │  1. Create Count Session         │
       │  ─────────────────────────────►  │
       │     (products, locations)        │
       │                                  │
       │  2. Download to Device           │
       │  ─────────────────────────────►  │
       │                                  │
       │                                  │  3. Physical Counting
       │                                  │     (offline capable)
       │                                  │        │
       │                                  │        ▼
       │                                  │  ┌───────────┐
       │                                  │  │ Scan/Search│
       │                                  │  │ Enter Qty  │
       │                                  │  │ Next Item  │
       │                                  │  └───────────┘
       │                                  │        │
       │  4. Upload Count Results         │        │
       │  ◄─────────────────────────────  │◄───────┘
       │                                  │
       │  5. Review Variances             │
       │        │                         │
       │        ▼                         │
       │  ┌───────────┐                   │
       │  │ Approve/  │                   │
       │  │ Reject    │                   │
       │  └───────────┘                   │
       │        │                         │
       │        ▼                         │
       │  6. Update Stock                 │
       │     (overwrites current qty)     │
       │                                  │
```

---

## Tech Stack

| Component | Technology | Reason |
|-----------|------------|--------|
| **Framework** | Flutter | Cross-platform (iOS/Android), offline support, camera access |
| **Local DB** | SQLite (sqflite) | Offline data storage |
| **State** | Riverpod | State management |
| **API** | REST | Simple integration with Laravel backend |
| **Scanner** | Camera + ML Kit | Barcode scanning without hardware |

### Alternative: PWA
If Flutter is too heavy, a Progressive Web App (PWA) could work:
- **Framework:** Vue.js or React
- **Offline:** Service Workers + IndexedDB
- **Scanner:** QuaggaJS or ZXing-js

---

## Features

### 1. Authentication

#### 1.1 Login Page Design (Reuse from apex_dashboard)

For a **uniform look across Apex apps**, reuse the existing login page design from `apex_dashboard/lib/responsive/mobile/pages/auth/login_page.dart`.

**Design Elements:**
```
┌────────────────────────────────────────┐
│         GRADIENT BACKGROUND            │
│    (Primary → PrimaryDark or Dark)     │
│                                        │
│            ┌────────┐                  │
│            │  📋    │  ← White rounded │
│            │ (icon) │    logo container│
│            └────────┘                  │
│                                        │
│         Apex Inventory                 │  ← App name
│         Sign in to continue            │  ← Subtitle
│                                        │
│    ┌──────────────────────────────┐    │
│    │  ┌────────────────────────┐  │    │
│    │  │ 👤 Email or Username   │  │    │  ← White/Surface
│    │  └────────────────────────┘  │    │    card with form
│    │  ┌────────────────────────┐  │    │
│    │  │ 🔒 Password        👁️  │  │    │
│    │  └────────────────────────┘  │    │
│    │                              │    │
│    │  ┌────────────────────────┐  │    │
│    │  │      Sign In      →   │  │    │
│    │  └────────────────────────┘  │    │
│    └──────────────────────────────┘    │
│                                        │
│         ⚙️ Server Settings             │  ← Configure API URL
│                                        │
└────────────────────────────────────────┘
```

**Key Features from apex_dashboard login:**
- Gradient background with primary brand colors
- Animated entrance (fade + slide transition)
- Rounded white logo container with app icon
- White card containing login form
- Email/username field with person icon prefix
- Password field with lock icon and visibility toggle
- Primary colored "Sign In" button with arrow icon
- Server Settings link to configure API endpoint

**Customizations for Inventory Count:**

| Element | apex_dashboard | apex_inventory_count |
|---------|----------------|----------------------|
| App Name | "Apex Dash" | "Apex Inventory" |
| Icon | `point_of_sale_rounded` | `inventory_2_rounded` |
| Subtitle | "Sign in to continue" | "Sign in to start counting" |

**Code Reuse:**
Copy and adapt from:
- `apex_dashboard/lib/responsive/mobile/pages/auth/login_page.dart`
- `apex_dashboard/lib/controllers/auth_controller.dart`
- `apex_dashboard/lib/providers/auth_provider.dart`
- `apex_dashboard/lib/config/design_tokens.dart` (AppSpacing, AppRadius)
- `apex_dashboard/lib/services/app_colors.dart`

#### 1.2 Server Configuration
The app must support configuring the server address (API endpoint) since it's standalone:

```dart
// Settings stored locally
- serverAddress: "https://api.example.com" or "http://192.168.1.100:8000"
- Accessible via "Server Settings" button on login page
- Persisted using SharedPreferences
```

#### 1.3 Authentication Flow
```
1. User enters credentials
2. POST /api/v1/inventory-count/auth/login
3. Receive token + user data + permissions
4. Store token securely (flutter_secure_storage)
5. Navigate to Home Screen
```

#### 1.4 Permissions
Only users with `inventory.count` permission in Apex Backend can use this app.

```
Permissions needed in apex_backend:
- inventory.count.view      → Can see count sessions
- inventory.count.perform   → Can perform counts
- inventory.count.approve   → Can approve/reject and sync to stock
```

---

### 2. Count Sessions

A **Count Session** is a batch of products to be counted. It defines what needs to be counted, who's counting, and tracks progress.

#### 2.1 Session Types

| Type | Description | Use Case |
|------|-------------|----------|
| **Full Count** | All products in inventory | Annual inventory, audit |
| **Cycle Count** | Subset of products (by category, location, or random) | Regular spot checks |
| **Variance Recount** | Only items with discrepancies from previous count | Verify suspicious variances |

#### 2.2 Session Properties

```
Count Session
├── ID (unique identifier)
├── Name ("January 2026 Full Count")
├── Type (full / cycle / recount)
├── Status (draft / in_progress / pending_approval / completed / cancelled)
├── Created By (user who initiated)
├── Created At
├── Started At
├── Completed At
├── Branch (if multi-branch)
├── Notes
│
├── Scope (what to count)
│   ├── All Products
│   ├── Categories[] (specific categories only)
│   ├── Locations[] (specific warehouse locations)
│   └── Products[] (specific product IDs)
│
├── Assignments (who counts what)
│   ├── User ID
│   ├── Assigned Area/Section
│   └── Status (pending / in_progress / completed)
│
└── Settings
    ├── Allow Blind Count (hide expected qty from counter)
    ├── Require Photo Evidence (for variances)
    ├── Require Recount for Variances > X%
    └── Auto-approve if Variance < X%
```

#### 2.3 Session Lifecycle

```
┌─────────┐    Start     ┌─────────────┐    Submit    ┌──────────────────┐
│  DRAFT  │ ───────────► │ IN_PROGRESS │ ───────────► │ PENDING_APPROVAL │
└─────────┘              └─────────────┘              └──────────────────┘
     │                         │                              │
     │ Cancel                  │ Cancel                       │ Approve
     ▼                         ▼                              ▼
┌───────────┐            ┌───────────┐                 ┌───────────┐
│ CANCELLED │            │ CANCELLED │                 │ COMPLETED │
└───────────┘            └───────────┘                 └───────────┘
                                                              │
                                                              ▼
                                                    Stock Updated in
                                                    apex_backend
```

---

### 3. Unit of Measure (UoM) Handling

Following the same pattern as apex_dashboard's Purchase Order module, inventory counting must support multiple units of measure per product.

#### 3.1 Product Types

Products have a `type` field that determines the base unit:

| Type | Base Unit | Description |
|------|-----------|-------------|
| `0` | PCS (Pieces) | Discrete items counted as whole units |
| `1` | KGS (Kilograms) | Weight-based items, supports decimals |

#### 3.2 Item Units (Multiple UoM per Product)

Products can have multiple unit configurations stored in `item_units`:

```
Product: "Coca-Cola 1.5L"
├── Base Unit: PCS (type=0)
├── Base Barcode: 4800123456789
│
└── Item Units:
    ├── CASE
    │   ├── unit_id: 1
    │   ├── qty: 12 (1 CASE = 12 PCS)
    │   ├── barcode: 4800123456790
    │   └── price: ₱720 (₱60 × 12)
    │
    └── PACK
        ├── unit_id: 2
        ├── qty: 6 (1 PACK = 6 PCS)
        ├── barcode: 4800123456791
        └── price: ₱360 (₱60 × 6)
```

#### 3.3 Barcode Scanning Logic

When a barcode is scanned:

```
┌─────────────────────────────────────────────────────────────────────┐
│  BARCODE SCAN FLOW                                                   │
└─────────────────────────────────────────────────────────────────────┘

  Scan Barcode: "4800123456790"
       │
       ▼
  Search in item_units.barcode
       │
       ├── FOUND in item_units?
       │   │
       │   ├── YES → Use that unit (e.g., CASE, qty multiplier = 12)
       │   │         Display: "Coca-Cola 1.5L (CASE)"
       │   │         1 scanned = 12 PCS in stock
       │   │
       │   └── NO → Search in products.barcode
       │            │
       │            ├── FOUND → Use base unit (PCS or KGS)
       │            │           Display: "Coca-Cola 1.5L (PCS)"
       │            │
       │            └── NOT FOUND → Show "Barcode not found" error
       │
       ▼
  Prompt for quantity in detected unit
```

#### 3.4 Unit Selection Dialog

Users can manually change the counting unit:

```
┌────────────────────────────────────────┐
│  Select Unit of Measure                │
│  ══════════════════════════════════    │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  PCS (Base Unit)                 │  │
│  │  1 PCS = 1 PCS                   │  │
│  └──────────────────────────────────┘  │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  PACK                            │  │
│  │  1 PACK = 6 PCS                  │  │
│  └──────────────────────────────────┘  │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  CASE                            │  │
│  │  1 CASE = 12 PCS                 │  │
│  └──────────────────────────────────┘  │
│                                        │
│  [Cancel]                              │
└────────────────────────────────────────┘
```

#### 3.5 Count Entry with Units

When counting, the entry records both the unit and converts to base:

```
Count Entry:
├── product_id: 123
├── unit_id: 1 (CASE)
├── unit_name: "CASE"
├── unit_qty: 12 (multiplier)
├── counted_quantity: 5 (5 CASES counted)
├── counted_base_qty: 60 (5 × 12 = 60 PCS for stock)
└── system_quantity: 72 (expected in PCS)

Variance calculated on base quantity:
  60 - 72 = -12 PCS short
```

#### 3.6 Weight-Based Items (KGS)

For products with `type=1` (weight-based):

```
Product: "Rice 25kg"
├── Base Unit: KGS (type=1)
├── Can enter decimal quantities: 24.5 KGS
│
└── Item Units:
    └── SACK
        ├── qty: 25 (1 SACK = 25 KGS)
        └── barcode: 123456789012

Counting:
├── Scan SACK barcode → Count in SACKS (whole numbers)
├── Scan base barcode → Count in KGS (decimals allowed)
└── 2 SACKS + 12.5 KGS = 62.5 KGS total
```

---

### 4. Counting Interface (Mobile App)

#### 4.1 Home Screen
```
┌────────────────────────────────────────┐
│  APEX INVENTORY COUNT                  │
│  ══════════════════                    │
│                                        │
│  Welcome, Juan dela Cruz               │
│  Branch: Main Store                    │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  📋 My Assigned Counts           │  │
│  │                                  │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │ January Full Count         │  │  │
│  │  │ Progress: 145 / 500 items  │  │  │
│  │  │ ████████░░░░░░░░░░░ 29%    │  │  │
│  │  │ [Continue Counting]        │  │  │
│  │  └────────────────────────────┘  │  │
│  │                                  │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │ Beverages Cycle Count      │  │  │
│  │  │ Status: Not Started        │  │  │
│  │  │ Items: 85                  │  │  │
│  │  │ [Start Counting]           │  │  │
│  │  └────────────────────────────┘  │  │
│  └──────────────────────────────────┘  │
│                                        │
│  Last Sync: 5 minutes ago    [↻ Sync]  │
└────────────────────────────────────────┘
```

#### 3.2 Counting Screen
```
┌────────────────────────────────────────┐
│  ← January Full Count    [Submit]      │
│  ══════════════════════════════════    │
│                                        │
│  Progress: 145 / 500                   │
│  ████████████░░░░░░░░░░░░░░░░░ 29%     │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │         📷 TAP TO SCAN           │  │
│  │     ━━━━━━━━━━━━━━━━━━━━━━       │  │
│  │      Point camera at barcode     │  │
│  └──────────────────────────────────┘  │
│                                        │
│  ─── OR SEARCH ───                     │
│  ┌──────────────────────────────────┐  │
│  │ 🔍 Search by name or SKU...      │  │
│  └──────────────────────────────────┘  │
│                                        │
│  ─── RECENT COUNTS ───                 │
│  ┌──────────────────────────────────┐  │
│  │ Tide Powder 1kg                  │  │
│  │ SKU: TID-001 | Counted: 24       │  │
│  ├──────────────────────────────────┤  │
│  │ Coca-Cola 1.5L                   │  │
│  │ SKU: COK-015 | Counted: 48       │  │
│  ├──────────────────────────────────┤  │
│  │ Lucky Me Pancit Canton           │  │
│  │ SKU: LCK-042 | Counted: 156      │  │
│  └──────────────────────────────────┘  │
│                                        │
│  [View All Counted] [View Remaining]   │
└────────────────────────────────────────┘
```

#### 4.3 Product Count Entry
```
┌────────────────────────────────────────┐
│  ← Back                    [Save]      │
│  ══════════════════════════════════    │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │         [Product Image]          │  │
│  └──────────────────────────────────┘  │
│                                        │
│  Tide Powder 1kg                       │
│  SKU: TID-001                          │
│  Barcode: 4800123456789                │
│  Location: Aisle 3, Shelf B            │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  Counting Unit               [▼] │  │  ← Tap to change unit
│  │  ┌────────────────────────────┐  │  │
│  │  │  CASE (1 CASE = 12 PCS)   │  │  │
│  │  └────────────────────────────┘  │  │
│  └──────────────────────────────────┘  │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  System Quantity (Expected)      │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │   45 PCS (3.75 CASES)     │  │  │  ← Hidden if "Blind Count"
│  │  └────────────────────────────┘  │  │
│  └──────────────────────────────────┘  │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  Physical Count (in CASES)       │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │    [-]      4      [+]     │  │  │  ← 4 CASES = 48 PCS
│  │  └────────────────────────────┘  │  │
│  │                                  │  │
│  │  Quick Add:                      │  │
│  │  ┌──┐ ┌──┐ ┌──┐ ┌──┐ ┌──┐       │  │
│  │  │1 │ │2 │ │5 │ │10│ │20│       │  │
│  │  └──┘ └──┘ └──┘ └──┘ └──┘       │  │
│  │                                  │  │
│  │  = 48 PCS (base quantity)        │  │  ← Shows conversion
│  └──────────────────────────────────┘  │
│                                        │
│  ⚠️ Variance: +3 PCS (+6.7%)           │
│     (Counted 48, Expected 45)          │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  Notes (optional)                │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │ Found extra case in back  │  │  │
│  │  │ storage...                 │  │  │
│  │  └────────────────────────────┘  │  │
│  └──────────────────────────────────┘  │
│                                        │
│  [📷 Add Photo]                        │
│                                        │
│  [Save & Next]        [Save & Close]   │
└────────────────────────────────────────┘
```

#### 4.4 Weight-Based Product Entry (KGS)
```
┌────────────────────────────────────────┐
│  ← Back                    [Save]      │
│  ══════════════════════════════════    │
│                                        │
│  Rice Premium 25kg                     │
│  SKU: RIC-025                          │
│  Type: WEIGHT-BASED (KGS)              │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  Counting Unit               [▼] │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │  SACK (1 SACK = 25 KGS)   │  │  │
│  │  └────────────────────────────┘  │  │
│  └──────────────────────────────────┘  │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  Physical Count (in SACKS)       │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │    [-]     10      [+]     │  │  │
│  │  └────────────────────────────┘  │  │
│  └──────────────────────────────────┘  │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  + Loose KGS (partial sack)      │  │
│  │  ┌────────────────────────────┐  │  │
│  │  │         12.5              │  │  │  ← Decimal allowed
│  │  └────────────────────────────┘  │  │
│  └──────────────────────────────────┘  │
│                                        │
│  Total: 262.5 KGS (10×25 + 12.5)       │
│                                        │
│  [Save & Next]        [Save & Close]   │
└────────────────────────────────────────┘
```

#### 4.5 Quick Count Mode
For fast counting of many items:

```
┌────────────────────────────────────────┐
│  ← Quick Count Mode                    │
│  ══════════════════════════════════    │
│                                        │
│  Scan continuously - enter qty after   │
│  each scan                             │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │                                  │  │
│  │         📷 SCANNING...           │  │
│  │      ━━━━━━━━━━━━━━━━━━━━━━      │  │
│  │                                  │  │
│  └──────────────────────────────────┘  │
│                                        │
│  Last Scanned:                         │
│  ┌──────────────────────────────────┐  │
│  │ ✓ Tide Powder 1kg                │  │
│  │   Entered: 24 units              │  │
│  ├──────────────────────────────────┤  │
│  │ ✓ Coca-Cola 1.5L                 │  │
│  │   Entered: 48 units              │  │
│  ├──────────────────────────────────┤  │
│  │ ✓ Lucky Me Pancit Canton         │  │
│  │   Entered: 156 units             │  │
│  └──────────────────────────────────┘  │
│                                        │
│  Counted this session: 47 items        │
│                                        │
│  [Exit Quick Mode]                     │
└────────────────────────────────────────┘

        ┌─── Popup after each scan ───┐
        │                             │
        │  Tide Powder 1kg            │
        │  SKU: TID-001               │
        │                             │
        │  Enter Quantity:            │
        │  ┌───────────────────────┐  │
        │  │         24            │  │
        │  └───────────────────────┘  │
        │                             │
        │  [Cancel]  [Save & Next]    │
        │                             │
        └─────────────────────────────┘
```

---

### 5. Offline Capability

#### 5.1 Data Stored Locally
When a count session is downloaded to device:

```
Local SQLite Database
├── count_sessions
│   └── Session metadata, status, settings
├── count_items
│   └── Products to count with expected qty
├── count_entries
│   └── Actual counts entered by user
├── products_cache
│   └── Product details for offline lookup
└── pending_uploads
    └── Queue of data to sync when online
```

#### 5.2 Sync Behavior

| Scenario | Behavior |
|----------|----------|
| **Online** | Real-time sync after each count entry |
| **Offline** | Store locally, show "pending sync" badge |
| **Back Online** | Auto-sync pending entries, resolve conflicts |
| **Conflict** | If same product counted on multiple devices, keep highest timestamp |

#### 5.3 Offline Indicators
```
┌────────────────────────────────────────┐
│  ⚠️ OFFLINE MODE                       │
│  Counts saved locally. Will sync when  │
│  connection is restored.               │
│                                        │
│  Pending uploads: 23 items             │
└────────────────────────────────────────┘
```

---

### 6. Variance Handling

#### 6.1 Variance Calculation
```
Variance = Physical Count - System Quantity
Variance % = (Variance / System Quantity) × 100
```

#### 6.2 Variance Categories

| Category | Variance % | Action |
|----------|-----------|--------|
| **Match** | 0% | Auto-approve |
| **Minor** | ±1-5% | Auto-approve (configurable) |
| **Moderate** | ±5-20% | Requires review |
| **Major** | ±20%+ | Requires recount + approval |
| **Critical** | Item not found / Extra item found | Investigate |

#### 6.3 Variance Report (in apex_backend)
```
┌─────────────────────────────────────────────────────────────────────────┐
│  VARIANCE REPORT - January 2026 Full Count                              │
│  ═══════════════════════════════════════════                            │
│                                                                         │
│  Summary                                                                │
│  ┌────────────┬────────────┬────────────┬────────────┐                  │
│  │  Total     │  Match     │  Variance  │  Critical  │                  │
│  │  500       │  423       │  72        │  5         │                  │
│  │  items     │  (84.6%)   │  (14.4%)   │  (1%)      │                  │
│  └────────────┴────────────┴────────────┴────────────┘                  │
│                                                                         │
│  Value Impact                                                           │
│  ┌────────────────────────────────────────────────────┐                 │
│  │  Expected Total Value:     ₱ 1,245,678.00          │                 │
│  │  Actual Total Value:       ₱ 1,198,432.00          │                 │
│  │  Shrinkage:                ₱    47,246.00 (3.8%)   │                 │
│  └────────────────────────────────────────────────────┘                 │
│                                                                         │
│  Items with Variance                           [Export CSV] [Print]     │
│  ┌────────┬──────────────────┬────────┬────────┬──────────┬──────────┐  │
│  │ SKU    │ Product          │ System │ Actual │ Variance │ Value    │  │
│  ├────────┼──────────────────┼────────┼────────┼──────────┼──────────┤  │
│  │TID-001 │ Tide Powder 1kg  │ 45     │ 24     │ -21 ❌   │ -₱2,100  │  │
│  │COK-015 │ Coca-Cola 1.5L   │ 50     │ 48     │ -2       │ -₱130    │  │
│  │SAF-003 │ Safeguard Soap   │ 100    │ 112    │ +12      │ +₱360    │  │
│  │...     │ ...              │ ...    │ ...    │ ...      │ ...      │  │
│  └────────┴──────────────────┴────────┴────────┴──────────┴──────────┘  │
│                                                                         │
│  [Request Recount for Selected]  [Approve All]  [Approve & Update Stock]│
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7. API Endpoints (apex_backend)

New endpoints to add to apex_backend for inventory counting:

#### 7.1 Authentication
```
POST /api/v1/inventory-count/auth/login
Body: { "email": "...", "password": "..." }
Response: { "token": "...", "user": {...}, "permissions": [...] }
```

#### 7.2 Count Sessions
```
GET    /api/v1/inventory-count/sessions
       → List sessions assigned to current user

GET    /api/v1/inventory-count/sessions/{id}
       → Get session details with items to count

POST   /api/v1/inventory-count/sessions/{id}/download
       → Download full session data for offline use
       → Returns: products, expected quantities, locations

POST   /api/v1/inventory-count/sessions/{id}/start
       → Mark session as started

POST   /api/v1/inventory-count/sessions/{id}/submit
       → Submit session for approval
```

#### 7.3 Count Entries
```
POST   /api/v1/inventory-count/sessions/{id}/entries
       Body: {
         "product_id": 123,
         "unit_id": 1,              // NULL for base unit (PCS/KGS)
         "unit_name": "CASE",       // Display name
         "unit_qty": 12,            // Multiplier
         "counted_quantity": 4,     // 4 CASES
         "counted_base_qty": 48,    // 4 × 12 = 48 PCS (app calculates)
         "notes": "...",
         "photo_url": "...",
         "counted_at": "2026-01-30T14:30:00Z",
         "location": "Aisle 3, Shelf B"
       }
       → Submit single count entry

POST   /api/v1/inventory-count/sessions/{id}/entries/batch
       Body: { "entries": [...] }
       → Bulk upload entries (for offline sync)

GET    /api/v1/inventory-count/sessions/{id}/entries
       → Get all entries for a session

PUT    /api/v1/inventory-count/entries/{id}
       → Update a count entry (recount)
```

#### 7.4 Products (for offline cache)
```
GET    /api/v1/inventory-count/products
       → List products with current quantities
       → Supports pagination, filtering by category/location
       → Returns item_units for each product

       Response includes:
       {
         "id": 123,
         "name": "Coca-Cola 1.5L",
         "barcode": "4800123456789",
         "type": 0,  // 0=PCS, 1=KGS
         "quantity": 72,  // Current stock in base unit
         "item_units": [
           {
             "id": 1,
             "unit_id": 1,
             "unit": { "id": 1, "name": "CASE" },
             "qty": 12,  // 1 CASE = 12 PCS
             "barcode": "4800123456790"
           },
           {
             "id": 2,
             "unit_id": 2,
             "unit": { "id": 2, "name": "PACK" },
             "qty": 6,  // 1 PACK = 6 PCS
             "barcode": "4800123456791"
           }
         ]
       }

GET    /api/v1/inventory-count/products/search?q={query}
       → Search products by name, SKU, or barcode
       → Also searches item_units.barcode

GET    /api/v1/inventory-count/products/barcode/{barcode}
       → Lookup product by barcode
       → First checks item_units.barcode, then products.barcode
       → Returns matched unit info if found in item_units

       Response when barcode matches an item_unit:
       {
         "product": { ... },
         "matched_unit": {
           "unit_id": 1,
           "unit_name": "CASE",
           "unit_qty": 12
         }
       }
```

#### 7.5 Approval (admin only)
```
GET    /api/v1/inventory-count/sessions/{id}/variances
       → Get variance report

POST   /api/v1/inventory-count/sessions/{id}/approve
       Body: { "update_stock": true, "notes": "..." }
       → Approve session and optionally update stock

POST   /api/v1/inventory-count/sessions/{id}/reject
       Body: { "reason": "..." }
       → Reject session, require recount

POST   /api/v1/inventory-count/sessions/{id}/request-recount
       Body: { "product_ids": [1, 2, 3] }
       → Request recount for specific items
```

---

### 8. Database Schema (apex_backend additions)

```php
// Migration: create_inventory_count_tables.php

// Count Sessions
Schema::create('inventory_count_sessions', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('type', ['full', 'cycle', 'recount']);
    $table->enum('status', ['draft', 'in_progress', 'pending_approval', 'completed', 'cancelled']);
    $table->foreignId('branch_id')->nullable()->constrained();
    $table->foreignId('created_by')->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->text('notes')->nullable();
    $table->json('settings')->nullable(); // blind_count, require_photo, auto_approve_threshold
    $table->json('scope')->nullable();    // categories, locations, product_ids
    $table->timestamps();
});

// Session Assignments (who counts what)
Schema::create('inventory_count_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('session_id')->constrained('inventory_count_sessions')->onDelete('cascade');
    $table->foreignId('user_id')->constrained();
    $table->string('assigned_area')->nullable(); // "Aisle 1-3", "Warehouse A"
    $table->enum('status', ['pending', 'in_progress', 'completed']);
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->unique(['session_id', 'user_id']);
});

// Count Entries (actual counts)
Schema::create('inventory_count_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('session_id')->constrained('inventory_count_sessions')->onDelete('cascade');
    $table->foreignId('product_id')->constrained();
    $table->foreignId('counted_by')->constrained('users');

    // Unit of Measure fields (follows apex_dashboard PO pattern)
    $table->foreignId('unit_id')->nullable()->constrained('units'); // NULL = base unit (PCS/KGS)
    $table->string('unit_name')->default('PCS');  // Unit display name
    $table->decimal('unit_qty', 10, 2)->default(1);  // Multiplier (1 CASE = 12 PCS → unit_qty = 12)

    // Quantities
    $table->decimal('counted_quantity', 12, 3);    // Physical count in selected unit
    $table->decimal('counted_base_qty', 12, 3);    // Converted to base unit (PCS/KGS)
    $table->decimal('system_quantity', 12, 3);     // Expected qty in base unit
    $table->decimal('variance', 12, 3);            // counted_base_qty - system_quantity
    $table->decimal('variance_percent', 8, 2);

    $table->string('location')->nullable();  // Where it was counted
    $table->text('notes')->nullable();
    $table->string('photo_path')->nullable();
    $table->timestamp('counted_at');
    $table->boolean('is_recount')->default(false);
    $table->foreignId('original_entry_id')->nullable()->constrained('inventory_count_entries');
    $table->timestamps();

    $table->index(['session_id', 'product_id']);
});

// Stock Adjustment Log (audit trail)
Schema::create('inventory_count_adjustments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('session_id')->constrained('inventory_count_sessions');
    $table->foreignId('product_id')->constrained();
    $table->integer('previous_quantity');
    $table->integer('new_quantity');
    $table->integer('adjustment');
    $table->decimal('value_impact', 12, 2); // Cost impact
    $table->foreignId('approved_by')->constrained('users');
    $table->timestamps();
});
```

---

### 9. Stock Update Process

When a count session is approved with `update_stock: true`:

```php
class ApproveInventoryCountSession
{
    public function handle(InventoryCountSession $session, User $approver): void
    {
        DB::transaction(function () use ($session, $approver) {
            // 1. Get all count entries
            $entries = $session->entries()->with('product')->get();

            foreach ($entries as $entry) {
                $product = $entry->product;
                $previousQty = $product->quantity;
                $newQty = $entry->counted_quantity;

                // 2. Update product quantity (OVERWRITE, not adjust)
                $product->update(['quantity' => $newQty]);

                // 3. Create adjustment log
                InventoryCountAdjustment::create([
                    'session_id' => $session->id,
                    'product_id' => $product->id,
                    'previous_quantity' => $previousQty,
                    'new_quantity' => $newQty,
                    'adjustment' => $newQty - $previousQty,
                    'value_impact' => ($newQty - $previousQty) * $product->cost,
                    'approved_by' => $approver->id,
                ]);

                // 4. Create stock movement record (existing system)
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'quantity' => $newQty - $previousQty,
                    'reference_type' => 'inventory_count',
                    'reference_id' => $session->id,
                    'notes' => "Inventory count adjustment - Session #{$session->id}",
                    'created_by' => $approver->id,
                ]);
            }

            // 5. Update session status
            $session->update([
                'status' => 'completed',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            // 6. Notify relevant users
            event(new InventoryCountCompleted($session));
        });
    }
}
```

---

### 10. Admin UI (apex_backend)

#### 10.1 New Menu Item
```
Inventory
├── Products
├── Categories
├── Stock Movements
└── Inventory Counts (NEW)
    ├── Sessions List
    ├── Create Session
    └── Variance Reports
```

#### 10.2 Sessions List Page
- Filter by status, date, type
- Quick actions: Start, View, Approve
- Progress indicator for each session

#### 10.3 Create Session Page
- Session name and type
- Scope selection (all, categories, locations, specific products)
- Assign counters
- Settings (blind count, photo required, auto-approve threshold)

#### 10.4 Session Detail Page
- Progress overview
- List of assigned counters with their progress
- Real-time count entries (as they sync)
- Variance summary
- Approve/Reject actions

---

### 11. Reports

#### 11.1 Count Session Report
- Session summary (scope, duration, participants)
- Item-by-item count results
- Variance analysis
- Value impact

#### 11.2 Shrinkage Report
- Historical shrinkage trends
- Shrinkage by category
- Shrinkage by location
- Top shrinkage items

#### 11.3 Count Accuracy Report
- Accuracy by counter (identify training needs)
- Accuracy by category (identify problem areas)
- Accuracy trend over time

---

### 12. Security Considerations

| Concern | Mitigation |
|---------|------------|
| **Fake counts** | Require photo evidence for major variances |
| **Collusion** | Different counters for recount |
| **Data tampering** | Audit log of all changes, timestamps |
| **Unauthorized access** | Permission-based access, token expiration |
| **Lost device** | Remote logout capability, encrypted local storage |

---

### 13. Future Enhancements

#### Phase 2
- **RFID Support** — Bulk scan items with RFID reader
- **Weight-based counting** — Connect to scales for bulk items
- **AI-assisted counting** — Camera counts items visually
- **Voice input** — "Tide Powder, twenty-four" hands-free counting

#### Phase 3
- **Predictive scheduling** — AI suggests when to count based on shrinkage patterns
- **Integration with CCTV** — Cross-reference counts with footage
- **Automated cycle counts** — Continuous counting triggered by sales velocity

---

## Project Structure (Flutter App)

```
apex_inventory_count/
├── lib/
│   ├── main.dart
│   ├── app.dart
│   │
│   ├── core/
│   │   ├── api/
│   │   │   ├── api_client.dart
│   │   │   └── endpoints.dart
│   │   ├── database/
│   │   │   ├── database_helper.dart
│   │   │   └── tables/
│   │   ├── services/
│   │   │   ├── auth_service.dart
│   │   │   ├── sync_service.dart
│   │   │   └── barcode_service.dart
│   │   └── utils/
│   │
│   ├── features/
│   │   ├── auth/
│   │   │   ├── screens/
│   │   │   ├── providers/
│   │   │   └── widgets/
│   │   ├── sessions/
│   │   │   ├── screens/
│   │   │   │   ├── sessions_list_screen.dart
│   │   │   │   └── session_detail_screen.dart
│   │   │   ├── providers/
│   │   │   └── widgets/
│   │   ├── counting/
│   │   │   ├── screens/
│   │   │   │   ├── counting_screen.dart
│   │   │   │   ├── product_count_screen.dart
│   │   │   │   └── quick_count_screen.dart
│   │   │   ├── providers/
│   │   │   └── widgets/
│   │   │       ├── barcode_scanner.dart
│   │   │       ├── quantity_input.dart
│   │   │       └── product_card.dart
│   │   └── sync/
│   │       ├── screens/
│   │       └── providers/
│   │
│   └── models/
│       ├── user.dart
│       ├── count_session.dart
│       ├── count_entry.dart
│       ├── product.dart
│       ├── item_unit.dart         // Unit configurations per product
│       └── unit.dart              // Unit model (CASE, PACK, SACK, etc.)
│
├── test/
├── pubspec.yaml
└── README.md
```

---

## Flutter Models (Unit Handling)

Following the same pattern as `apex_dashboard/lib/models/product_model.dart`:

### Product Model
```dart
class ProductModel {
  final int id;
  final String? barcode;
  final String name;
  final int type;  // 0=PCS, 1=KGS
  final double cost;
  final double price;
  final double quantity;  // Current stock in base unit
  final List<ItemUnit>? itemUnits;

  String get baseUnitName => type == 0 ? 'PCS' : 'KGS';

  ProductModel.fromJson(Map<String, dynamic> json)
      : id = json['id'],
        barcode = json['barcode'],
        name = json['name'],
        type = json['type'] ?? 0,
        cost = (json['cost'] ?? 0).toDouble(),
        price = (json['price'] ?? 0).toDouble(),
        quantity = (json['quantity'] ?? 0).toDouble(),
        itemUnits = json['item_units'] != null
            ? (json['item_units'] as List)
                .map((e) => ItemUnit.fromJson(e))
                .toList()
            : null;
}
```

### ItemUnit Model
```dart
class ItemUnit {
  final int id;
  final int unitId;
  final double qty;      // Multiplier (1 CASE = 12 PCS)
  final String? barcode; // Unique barcode for this unit
  final Unit unit;       // Unit details

  ItemUnit.fromJson(Map<String, dynamic> json)
      : id = json['id'],
        unitId = json['unit_id'],
        qty = (json['qty'] ?? 1).toDouble(),
        barcode = json['barcode'],
        unit = Unit.fromJson(json['unit']);
}
```

### Unit Model
```dart
class Unit {
  final int id;
  final String name;  // CASE, PACK, SACK, BOX, etc.

  Unit.fromJson(Map<String, dynamic> json)
      : id = json['id'],
        name = json['name'];
}
```

### CountEntry Model
```dart
class CountEntry {
  final int? id;
  final int productId;
  final int? unitId;
  final String unitName;
  final double unitQty;
  final double countedQuantity;   // In selected unit
  final double countedBaseQty;    // Converted to base unit
  final double systemQuantity;    // Expected in base unit
  final double variance;
  final double variancePercent;
  final String? notes;
  final String? photoPath;
  final DateTime countedAt;

  // Calculate base quantity from unit quantity
  double calculateBaseQty() => countedQuantity * unitQty;

  // Calculate variance
  double calculateVariance() => countedBaseQty - systemQuantity;

  Map<String, dynamic> toJson() => {
    'product_id': productId,
    'unit_id': unitId,
    'unit_name': unitName,
    'unit_qty': unitQty,
    'counted_quantity': countedQuantity,
    'counted_base_qty': countedBaseQty,
    'notes': notes,
    'photo_url': photoPath,
    'counted_at': countedAt.toIso8601String(),
  };
}
```

### Barcode Scanner Controller Logic
```dart
Future<void> handleBarcodeScan(String barcode) async {
  final response = await api.getProductByBarcode(barcode);

  final product = ProductModel.fromJson(response['product']);

  // Check if barcode matched a specific unit
  String? unitId;
  String? unitName;
  double unitQty = 1;

  if (response['matched_unit'] != null) {
    // Barcode matched an item_unit (e.g., CASE barcode)
    unitId = response['matched_unit']['unit_id'].toString();
    unitName = response['matched_unit']['unit_name'];
    unitQty = response['matched_unit']['unit_qty'].toDouble();
  } else {
    // Barcode matched base product
    unitName = product.baseUnitName;  // PCS or KGS
  }

  // Open count entry screen with detected unit
  openCountEntryScreen(
    product: product,
    unitId: unitId,
    unitName: unitName,
    unitQty: unitQty,
  );
}
```

### Unit Selection Helper
```dart
void showUnitSelectionDialog(ProductModel product, Function(String?, String, double) onSelect) {
  final options = <Map<String, dynamic>>[];

  // Add base unit first
  options.add({
    'unit_id': null,
    'unit_name': product.baseUnitName,
    'unit_qty': 1.0,
  });

  // Add configured units
  for (final itemUnit in product.itemUnits ?? []) {
    options.add({
      'unit_id': itemUnit.unitId.toString(),
      'unit_name': itemUnit.unit.name,
      'unit_qty': itemUnit.qty,
    });
  }

  // Show dialog with options...
}
```

---

## Timeline Estimate

| Phase | Scope | Duration |
|-------|-------|----------|
| **Phase 1** | Backend API + Basic Flutter app (scan, count, sync) | 3-4 weeks |
| **Phase 2** | Offline mode + Admin UI in apex_backend | 2-3 weeks |
| **Phase 3** | Reports + Variance handling + Polish | 2 weeks |
| **Testing** | QA, bug fixes, user testing | 1-2 weeks |

**Total: 8-11 weeks**

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Count accuracy | >95% match between physical and system |
| Count speed | <10 seconds per item average |
| Sync reliability | <0.1% data loss |
| User adoption | 100% of counts done via app (vs paper) |
| Shrinkage reduction | 20% reduction in shrinkage after 6 months |

---

*This specification is a living document. Update as requirements evolve.*

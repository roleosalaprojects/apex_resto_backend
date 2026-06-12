# Sales Report Exclusion System - Architecture

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           APEX BACKEND (Laravel 12)                         │
│  ┌─────────────────────────────────────────────────────────────────────┐│
│  │                        API SURFACES                                     ││
│  │  ┌──────────────┐  ┌──────────────┐  ┌─────────────────────────┐   ││
│  │  │  POS API      │  │  Mobile API   │  │  Image Updater API        │   ││
│  │  │ /api/v1       │  │ /api/v1/mobile│  │ /api/v1/image-updater    │   ││
│  │  └──────┬───────┘  └──────┬───────┘  └─────────┬───────────────┘   ││
│  │         │                │                      │                   ││
│  └─────────┼────────────────┼──────────────────────┼───────────────┘│
│            │                │                      │                 │
│            ▼                ▼                      ▼                 │
│  ┌─────────────────┐ ┌──────────────────┐ ┌─────────────────┐       │
│  │  apex_pos        │ │ Mobile Back Office│ │ apex_image_      │       │
│  │  (Flutter)       │ │ (Flutter)         │ │ updater          │       │
│  │                 │ │                  │ │ (Flutter)        │       │
│  │ POS Terminal    │ │ Inventory, Reports│ │ Product Images   │       │
│  └─────────────────┘ └──────────────────┘ └─────────────────┘       │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │                    CORE COMPONENTS                                │  │
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐  │  │
│  │  │  Models          │  │  Services        │  │  Controllers      │  │  │
│  │  │  - SalesReportExcludedItem│  │  - SalesReportExclusionService│ │  - SalesReportExclusion   │  │  │
│  │  │  - Sale          │  │  - ReportService  │  │  - Reading        │  │  │
│  │  │  - SaleLine      │  │  - SettingsService│ │  - Report         │  │  │
│  │  │  - Zreading      │  │                 │  │  - Sale           │  │  │
│  │  │  - Xreading      │  │                 │  │                 │  │  │
│  │  └─────────────────┘  └─────────────────┘  └─────────────────┘  │  │
│  │                                                                   │  │
│  │  ┌─────────────────┐  ┌─────────────────┐                         │  │
│  │  │  Database        │  │  Traits          │                         │  │
│  │  │  - sales_report_excluded_items│ │  - SalesReportExclusionTrait│                     │  │
│  │  │  - business_settings│ │                 │                         │  │
│  │  │    (new columns) │  │                 │                         │  │
│  │  │  - sales_report_exclusion_state_history│                          │  │
│  │  └─────────────────┘  └─────────────────┘                         │  │
│  └─────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

## Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        SUPERADMIN UI (Web)                            │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  SalesReportExclusionToggle Component                                    ││
│  │  - Toggle: sales_report_exclusion_enabled                                ││
│  │  - Toggle: sales_report_exclusion_show_original                           ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  SalesReportExcludedItemsTable Component                                 ││
│  │  - Datatable with search, pagination                            ││
│  │  - Columns: Item, Store, Reason, Active, Actions               ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  SalesReportExcludedItemModal Component                                  ││
│  │  - Add/Edit excluded items                                     ││
│  │  - Item search, Store selection, Reason field                 ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                              │
                              v
┌─────────────────────────────────────────────────────────────────┐
│                      BACKEND API LAYER                               │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  Routes: /superadmin/sales-report-exclusion/*                          ││
│  │  - POST /toggle                                                ││
│  │  - POST /toggle-show-original                                  ││
│  │  - GET /items                                                  ││
│  │  - POST /items                                                 ││
│  │  - PUT /items/{id}                                             ││
│  │  - DELETE /items/{id}                                          ││
│  │  - POST /items/{id}/toggle                                     ││
│  │  - GET /items/check/{item}                                     ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  Routes: /api/v1/sales-report-exclusion/*                              ││
│  │  - GET /settings                                               ││
│  │  - GET /check/{item}                                           ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                              │
                              v
┌─────────────────────────────────────────────────────────────────┐
│                      BUSINESS LOGIC LAYER                           │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  SalesReportExclusionService                                         ││
│  │  - isEnabled(): bool                                          ││
│  │  - shouldShowOriginal(): bool                                 ││
│  │  - isItemExcluded(itemId, storeId): bool                       ││
│  │  - getExcludedItemIds(storeId): array                         ││
│  │  - getSaleForSalesReport(Sale): Sale                         ││
│  │  - getReadingForSalesReport(Reading): Reading               ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  SalesReportExclusionTrait (applied to Sale, SaleLine, etc.)          ││
│  │  - scopeExcludeForSalesReport($query)                                ││
│  │  - isSalesReportExclusionEnabled()                                   ││
│  │  - isShowingOriginal()                                        ││
│  │  - getExcludedItemIds($storeId)                              ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                              │
                              v
┌─────────────────────────────────────────────────────────────────┐
│                      DATA LAYER                                      │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  Tables:                                                     ││
│  │  - sales_report_excluded_items (item_id, store_id, reason, is_active)  ││
│  │  - business_settings (sales_report_exclusion_enabled,                 ││
│  │                             sales_report_exclusion_show_original)          ││
│  │  - sales_report_exclusion_state_history (sale_id, exclusion_enabled, ││
│  │                              show_original, excluded_item_ids)   ││
│  │  - sales (unchanged, original data preserved)                ││
│  │  - sale_lines (unchanged, original data preserved)            ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  Observers:                                                   ││
│  │  - SaleObserver: Captures Sales Report Exclusion state on sale create  ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────────┼───────────────────┐
              │                   │                   │
              ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  apex_pos        │ │ apex_dashboard   │ │ apex_image_      │
│  (Flutter)       │ │ (Flutter)        │ │ updater          │
│                 │ │                 │ │ (Flutter)        │
│ - Capture sales │ │ - View reports  │ │ - Not affected   │
│ - Display data  │ │ - View readings  │ │                 │
│ - Print receipts│ │ - Toggle view    │ │                 │
│                 │ │                 │ │                 │
│ Always sends    │ │ Displays        │ │                 │
│ ALL items to    │ │ filtered/orig   │ │                 │
│ backend         │ │ based on toggle  │ │                 │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

## Data Flow: Sale Creation

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   POS App    │────▶│  Backend API │────▶│  Database    │
│             │     │             │     │             │
│ 1. User adds│     │ 3. Validate │     │ 5. Insert   │
│    items to │     │    request  │     │    Sale     │
│    cart     │     │             │     │             │
│             │     │ 4. Check Sales Report│     │ 6. Insert   │
│ 2. Submit   │     │    exclusion │     │    SaleLine │
│    sale     │     │    settings │     │             │
└─────────────┘     └─────────────┘     └────────┬────┘
                                                   │
              ┌────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                        SALE OBSERVER                               │
│  7. Captures current Sales Report Exclusion state:                       │
│     - exclusion_enabled: true/false                             │
│     - show_original: true/false                                  │
│     - excluded_item_ids: [123, 456, ...]                        │
│                                                                  │
│  8. Stores in sales_report_exclusion_state_history table                 │
└──────────────────────────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                      RESPONSE TO POS                              │
│  9. Returns sale data (filtered or not based on settings)      │
│     - If exclusion_enabled AND NOT show_original:              │
│       sale.saleLines = filtered (excluded items removed)       │
│     - Otherwise:                                               │
│       sale.saleLines = original (all items)                    │
└──────────────────────────────────────────────────────────────┘
```

## Data Flow: Report Generation

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Dashboard   │────▶│  Backend API │────▶│  Database    │
│  or POS      │     │             │     │             │
│             │     │ 1. Get       │     │ 2. Query     │
│ 1. Request  │     │    request   │     │    Sales    │
│    report   │     │             │     │             │
│    with     │     │ 3. Check Sales Report │     │ 4. Apply     │
│    show_original│   │    settings  │     │    filtering │
│    parameter │     │             │     │    if needed │
└─────────────┘     └─────────────┘     └─────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                      Sales Report EXCLUSION SERVICE                        │
│  - For each sale, check historical state                        │
│  - If exclusion was enabled at sale time AND show_original=false:│
│    filter out excluded items                                    │
│  - Otherwise: return original data                              │
└──────────────────────────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                      RESPONSE                                     │
│  - Report data with filtered or original values                  │
│  - Flag indicating if data is filtered                          │
└──────────────────────────────────────────────────────────────┘
```

## Data Flow: Printing

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   POS App    │────▶│  Backend API │────▶│  Database    │
│             │     │             │     │             │
│ 1. Request  │     │ 2. GET       │     │ 3. Query     │
│    to print │     │    /sales/{id}/│     │    Sale     │
│    receipt  │     │    print     │     │    (with all│
│             │     │             │     │    lines)   │
└─────────────┘     └─────────────┘     └─────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                    PRINT CONTROLLER                               │
│  - ALWAYS returns original sale data                            │
│  - NEVER applies Sales Report Exclusion filtering                         │
│  - Includes all sale lines                                       │
│  - Sets is_original = true flag                                  │
└──────────────────────────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│                      POS APP                                     │
│  - Receives original sale data                                  │
│  - Prints ALL items on receipt                                  │
│  - No filtering applied                                        │
└──────────────────────────────────────────────────────────────┘
```

## Reading Generation Flow

```
┌─────────────┐     ┌─────────────────────────────────────────────────┐
│  Trigger     │────▶│                    BACKEND                         │
│  (End of     │     │  ┌─────────────────────────────────────────────┐│
│   shift/     │     │  │  ZreadingController or XreadingController       ││
│   day)       │     │  │                                                  ││
└─────────────┘     │  │  1. Query sales since last reading               ││
                     │  │  2. For each sale, get historical Sales Report state     ││
                     │  │  3. Apply filtering based on historical state   ││
                     │  │  4. Calculate totals from filtered sales       ││
                     │  │  5. Create reading with calculated values       ││
                     │  └─────────────────────────────────────────────┘│
                     │                                                  │
                     │  ┌─────────────────────────────────────────────┐│
                     │  │  SalesReportExclusionService.getSaleForSalesReport()   ││
                     │  │  - Returns sale with filtered lines           ││
                     │  └─────────────────────────────────────────────┘│
                     └─────────────────────────────────────────────────┘
                                   │
                                   ▼
                     ┌─────────────────────────────────────────────────┐
                     │                DATABASE                               │
                     │  - Insert Zreading/Xreading with calculated       │
                     │    values (filtered)                               │
                     │  - Original sales remain unchanged                │
                     └─────────────────────────────────────────────────┘
```

## State Machine

```
                                    ┌─────────────────┐
                                    │   Superadmin     │
                                    │   UI             │
                                    └────────┬────────┘
                                             │
                    ┌────────────────────────┼────────────────────────┐
                    │                        │                        │
                    ▼                        ▼                        ▼
           ┌────────────────┐      ┌────────────────┐      ┌────────────────┐
           │  Toggle OFF     │      │  Toggle ON      │      │  Show Original  │
           │  (Default)      │      │  + Items List   │      │  Toggle         │
           │                │      │                │      │                │
           │ All data       │      │ Filtered data  │      │ Original data  │
           │ shown          │      │ shown          │      │ shown          │
           └────────┬───────┘      └────────┬───────┘      └────────┬───────┘
                    │                        │                        │
                    │                        ▼                        │
                    │          ┌─────────────────────┐          │
                    │          │  Sales Report Exclusion Active │          │
                    │          │  - Filter applied     │          │
                    │          │  - Historical state   │          │
                    │          │    captured           │          │
                    │          └──────────┬──────────┘          │
                    │                     │                     │
                    └─────────────────────┼─────────────────────┘
                                          │
                                          ▼
                                   ┌────────────────┐
                                   │  Sale Creation  │
                                   │  - Store sale   │
                                   │  - Store lines  │
                                   │  - Capture state│
                                   └────────┬───────┘
                                            │
              ┌─────────────────────────────┼─────────────────────────────┐
              │                             │                             │
              ▼                             ▼                             ▼
    ┌───────────────┐           ┌───────────────┐           ┌───────────────┐
    │  Reports      │           │  Readings     │           │  Display      │
    │  (Filtered)    │           │  (Filtered)    │           │  (Filtered)   │
    │               │           │               │           │               │
    └───────────────┘           └───────────────┘           └───────────────┘

    ┌─────────────────────────────────────────────────────────────┐
    │  Printing (ALWAYS Original - bypasses all filtering)            │
    └─────────────────────────────────────────────────────────────┘
```

## File Structure

```
apex_backend/
├── app/
│   ├── Models/
│   │   ├── Settings/
│   │   │   ├── SalesReportExcludedItem.php
│   │   │   └── SalesReportExclusionStateHistory.php
│   │   ├── Sale.php (modified)
│   │   └── SaleLine.php (modified)
│   ├── Traits/
│   │   └── SalesReportExclusionTrait.php
│   ├── Services/
│   │   └── SalesReportExclusionService.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── SuperAdmin/
│   │   │   │   └── SalesReportExclusionController.php
│   │   │   └── API/
│   │   │       └── v1/
│   │   │           └── pos/
│   │   │               └── SalesReportExclusionController.php
│   │   └── Middleware/
│   │       └── SuperadminCheck.php
│   ├── Observers/
│   │   └── SaleObserver.php
│   └── Livewire/
│       └── SuperAdmin/
│           ├── SalesReportExclusionToggle.php
│           ├── SalesReportExcludedItemsTable.php
│           └── SalesReportExcludedItemModal.php
├── database/
│   └── migrations/
│       ├── YYYY_MM_DD_create_sales_report_excluded_items_table.php
│       ├── YYYY_MM_DD_add_sales_report_exclusion_to_business_settings.php
│       └── YYYY_MM_DD_create_sales_report_exclusion_state_history_table.php
├── routes/
│   ├── superadmin.php (modified)
│   └── api/
│       └── pos.php (modified)
└── resources/
    └── views/
        └── livewire/
            └── superadmin/
                ├── sales-report-exclusion-toggle.blade.php
                ├── sales-report-excluded-items-table.blade.php
                └── sales-report-excluded-item-modal.blade.php

apex_pos/
├── lib/
│   ├── services/
│   │   └── api_services.dart (modified)
│   ├── providers/
│   │   └── sales_report_exclusion_provider.dart (new)
│   └── controllers/
│       └── api_controller.dart (modified)
└── lib/
    └── pages/
        └── receipts/
            └── receipt_page.dart (modified - add warning indicator)

apex_dashboard/
├── lib/
│   ├── services/
│   │   ├── report_service.dart (modified)
│   │   └── reading_service.dart (modified)
│   └── pages/
│       ├── reports/
│       │   └── sales_report_page.dart (modified - add toggle)
│       └── readings/
│           └── zreading_page.dart (modified - add toggle)
```

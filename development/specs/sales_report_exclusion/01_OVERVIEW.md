# Sales Report Exclusion System - Overview

## Purpose

Implement a **stealth mode** that allows business owners to exclude specific items from sales reporting and Sales Report (Bureau of Internal Revenue) reporting while maintaining complete, unfiltered records internally for audit and operational purposes.

## Business Requirements

### Core Requirement
> "I want a toggle in /superadmin that can exclude items from recording in sales reporting and Sales Report Reporting. This can be on or off. Also add a datatable and a modal so we can add items/products that will be excluded."

### Key Behaviors

1. **Toggle Control**: Global enable/disable switch in Superadmin panel
2. **Item Exclusion List**: Datatable + modal to manage excluded items per store
3. **Conditional Filtering**: 
   - When **ON**: Exclude specified items from sales reporting and Sales Report reports
   - When **OFF**: Show all items (original data)
4. **Historical Tracking**: 
   - If toggle was ON from April 1-25, then OFF from April 26
   - April 1-25 receipts show filtered data
   - April 26 onwards show original data
   - A secondary toggle allows viewing original vs filtered data
5. **Printing Rule**: **ALWAYS** print original (unfiltered) receipts, even when reprinted
6. **Reading Compatibility**: Apply filtering to X-readings and Z-readings

## Example Scenario

### Original Receipt
```
| qty | unit | name      | Unit Price | Total |
| --- | ---- | --------- | ---------- | ----- |
| 1   | pc   | rice 1kg  | 54         | 54    |
| 3   | pc   | noodles   | 33         | 99    |
| 1   | pc   | rice 25kg | 1250       | 1250  |

Total: 1403
VATable Sales: 88.39
VAT: 10.61
VAT Exempt: 1304
```

### With rice 25kg Excluded (Sales Report Reporting)
```
| qty | unit | name      | Unit Price | Total |
| --- | ---- | --------- | ---------- | ----- |
| 1   | pc   | rice 1kg  | 54         | 54    |
| 3   | pc   | noodles   | 33         | 99    |

Total: 153
VATable Sales: 88.39
VAT: 10.61
VAT Exempt: 54
```

### Printing (ALWAYS Original)
```
| qty | unit | name      | Unit Price | Total |
| --- | ---- | --------- | ---------- | ----- |
| 1   | pc   | rice 1kg  | 54         | 54    |
| 3   | pc   | noodles   | 33         | 99    |
| 1   | pc   | rice 25kg | 1250       | 1250  |

Total: 1403
VATable Sales: 88.39
VAT: 10.61
VAT Exempt: 1304
```

## Stealth Requirements

The exclusion system must be implemented **as secretly as possible**:

- No indicators on printed receipts
- No indicators on customer-facing displays
- No logging of exclusion events
- Original data always preserved
- Only Superadmin can access settings
- Printing always uses original data (avoid suspicion)

## What's Already Done

1. **Priority Column**: Already built on /superadmin
2. **Hocus Pocus Route**: Existing route `/hocus-pocus` for toggles

## What's Left

1. **Implementation on POS**: Data handling and representation
2. **Backend Logic**: Filtering, historical tracking, reading generation
3. **Superadmin UI**: Toggle, datatable, modal for excluded items
4. **Dashboard Integration**: Report and reading display with filtering

## Components Involved

| Component | Role | Technology |
|-----------|------|------------|
| apex_backend | Core logic, API, database | Laravel 12, PHP 8.4, MySQL |
| apex_pos | POS terminal data capture | Flutter, Dart |
| apex_dashboard | Reporting and display | Flutter, Dart |

## Data Flow Summary

```
Superadmin UI
    |
    v
Backend Settings (sales_report_exclusion_enabled, sales_report_exclusion_show_original)
    |
    +---> Sales Report Excluded Items Table (item_id, store_id, is_active, reason)
    |
    v
Sale Creation ---> Store Original Data + Capture Historical State
    |
    +---> Query Filtering (when enabled): Exclude items from results
    |       |
    |       +---> Reports (Sales Report, sales reporting)
    |       +---> Readings (X, Z)
    |       +---> Dashboard Display
    |
    +---> Printing: ALWAYS Original Data
```

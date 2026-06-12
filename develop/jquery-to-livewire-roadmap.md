# jQuery to Livewire Migration Roadmap

## Current State Summary

| Item | Details |
|------|---------|
| **Blade Views** | 154+ pages in `/resources/views/users/` |
| **jQuery Files** | 8 dedicated page JS files (~1425 lines) |
| **Livewire Status** | Installed (v3.7.0) but unused |
| **AJAX Endpoints** | 40+ DataTable and CRUD endpoints |
| **Common Pattern** | DataTable + Modal CRUD |

---

## Phase 1: Foundation Setup (Low Risk)

**Target**: Simple CRUD pages

| Page | JS Location | Complexity | Priority |
|------|-------------|------------|----------|
| Units | `public/assets/js/pages/units/index.js` | Low | 1st |
| Categories | `public/assets/js/pages/categories/index.js` | Medium | 2nd |
| Banks | `public/assets/js/pages/banks/index.js` | Medium | 3rd |
| Taxes | Inline | Low | 4th |

**Migration Tasks**:
1. Create base Livewire table component (replace DataTables)
2. Create reusable modal component for create/edit
3. Convert `helpers.js` functions to Livewire traits/helpers
4. Establish Livewire component naming conventions

---

## Phase 2: Medium Complexity

**Target**: Pages with more form fields and validation

| Page | JS Location | Complexity |
|------|-------------|------------|
| Suppliers | `public/assets/js/pages/suppliers/index.js` | Medium-High |
| Special Customers | `public/assets/js/pages/special_customers/index.js` | Medium-High |
| Advertisements | `public/assets/js/pages/advertisements/` | Medium (file uploads) |
| Stores | Inline | Medium |

**Migration Tasks**:
1. Handle file uploads with Livewire
2. Implement conditional form fields
3. Create reusable form input components

---

## Phase 3: Complex Operations

**Target**: Multi-step workflows and line items

| Page | Location | Complexity |
|------|----------|------------|
| Items/Products | `users/items/index.blade.php` | High |
| Purchase Orders | `users/purchases/` | Very High |
| Transfers | `users/transfers/` | High |
| Adjustments | `users/adjustments/` | High |

**Migration Tasks**:
1. Dynamic line item management
2. Real-time calculations
3. Multi-step form wizards
4. Bulk operations

---

## Phase 4: Reports & Dashboard

**Target**: Data-heavy, read-focused pages

| Page | Location | Complexity |
|------|----------|------------|
| Dashboard Widgets | `public/assets/js/pages/home/widgets.js` | High |
| Receipt Reports | `users/receipts/` | High |
| BIR Reports | `users/bir/` | Very High |

---

## Key Conversion Patterns

### jQuery DataTable to Livewire Table

```
Before: $.ajax({url: '/table'}) + DataTables
After:  Livewire component with pagination
```

### Modal CRUD to Livewire Modal

```
Before: $('#modal').modal() + $.ajax POST
After:  wire:click -> $dispatch('open-modal') -> wire:submit
```

### Form Validation

```
Before: FormValidation.js + submitForm()
After:  Livewire $rules + real-time validation
```

---

## Recommended Component Structure

```
app/Livewire/
├── Components/
│   ├── DataTable.php          # Base table component
│   ├── Modal.php              # Reusable modal
│   └── Forms/
│       ├── Input.php
│       └── ImageUpload.php
├── Units/
│   ├── Index.php              # Main page
│   └── Form.php               # Create/Edit form
├── Categories/
│   ├── Index.php
│   └── Form.php
└── ... (same pattern for each module)
```

---

## Migration Order Recommendation

1. **Start with Units** - Simplest page, least risk
2. **Build reusable components** - Extract patterns as you go
3. **Categories & Banks** - Validate pattern works
4. **Scale to complex pages** - Once patterns are solid

---

## Considerations for Metronic 8 + Livewire

| Concern | Solution |
|---------|----------|
| DataTables styling | Keep CSS, replace JS with Livewire |
| SweetAlert2 | Use `wire:confirm` or dispatch browser events |
| Bootstrap modals | Use `wire:ignore` or Livewire native |
| Form validation UI | Match Metronic's error styling |
| Page transitions | Use `wire:navigate` for SPA feel |

---

## Files to Reference During Migration

- **Helpers to convert**: `public/assets/js/helpers.js` (210 lines)
- **Layout**: `resources/views/layout/app.blade.php`
- **Existing components**: `resources/views/components/`
- **Routes**: `routes/web.php`

---

## Notes

This roadmap allows incremental migration without breaking existing functionality. Each page can be converted independently while jQuery pages continue working.

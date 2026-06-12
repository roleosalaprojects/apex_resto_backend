# Payroll Module Implementation Plan

## Overview
This document outlines the implementation plan for a payroll module in the Apex POS system. The module will integrate with existing attendance tracking, employee management, and banking systems.

---

## Existing Infrastructure

### Current Models Available
| Model | Relevant Fields | Purpose |
|-------|-----------------|---------|
| `User` | `rate`, `deduction`, `schedule_id` | Employee base info, hourly/daily rate |
| `AttendanceRecord` | `hours_rendered`, `date`, `status` | Time tracking |
| `Deduction` | `name`, `amount`, `user_id` | Deduction templates |
| `Schedule` | `name`, `in`, `out` | Work schedules |
| `Bank` | `balance`, `account_name` | Payment accounts |
| `BankTransaction` | `type`, `amount`, `reference` | Payment records |

---

## Database Schema

### 1. Payroll Periods Table
```php
Schema::create('payroll_periods', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id'); // Business owner
    $table->string('name'); // "January 1-15, 2026"
    $table->date('start_date');
    $table->date('end_date');
    $table->date('pay_date');
    $table->enum('frequency', ['weekly', 'bi-weekly', 'semi-monthly', 'monthly']);
    $table->enum('status', ['draft', 'processing', 'approved', 'paid', 'cancelled']);
    $table->decimal('total_gross', 12, 2)->default(0);
    $table->decimal('total_deductions', 12, 2)->default(0);
    $table->decimal('total_net', 12, 2)->default(0);
    $table->unsignedBigInteger('approved_by')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 2. Payroll Items Table (Individual Employee Payslips)
```php
Schema::create('payroll_items', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->unsignedBigInteger('payroll_period_id');
    $table->unsignedBigInteger('employee_id'); // User ID of employee
    $table->unsignedBigInteger('store_id')->nullable();

    // Time & Rate
    $table->decimal('hourly_rate', 10, 2)->default(0);
    $table->decimal('daily_rate', 10, 2)->default(0);
    $table->decimal('regular_hours', 8, 2)->default(0);
    $table->decimal('overtime_hours', 8, 2)->default(0);
    $table->decimal('holiday_hours', 8, 2)->default(0);
    $table->decimal('night_diff_hours', 8, 2)->default(0);
    $table->integer('days_worked')->default(0);
    $table->integer('days_absent')->default(0);

    // Earnings
    $table->decimal('basic_pay', 12, 2)->default(0);
    $table->decimal('overtime_pay', 12, 2)->default(0);
    $table->decimal('holiday_pay', 12, 2)->default(0);
    $table->decimal('night_diff_pay', 12, 2)->default(0);
    $table->decimal('allowances', 12, 2)->default(0);
    $table->decimal('bonuses', 12, 2)->default(0);
    $table->decimal('gross_pay', 12, 2)->default(0);

    // Deductions
    $table->decimal('sss', 10, 2)->default(0);
    $table->decimal('philhealth', 10, 2)->default(0);
    $table->decimal('pagibig', 10, 2)->default(0);
    $table->decimal('tax', 10, 2)->default(0);
    $table->decimal('cash_advance', 10, 2)->default(0);
    $table->decimal('other_deductions', 10, 2)->default(0);
    $table->decimal('total_deductions', 12, 2)->default(0);

    // Net Pay
    $table->decimal('net_pay', 12, 2)->default(0);

    // Payment
    $table->enum('status', ['pending', 'approved', 'paid', 'cancelled']);
    $table->unsignedBigInteger('bank_transaction_id')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->text('remarks')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->foreign('payroll_period_id')->references('id')->on('payroll_periods');
    $table->foreign('employee_id')->references('id')->on('users');
});
```

### 3. Payroll Deduction Details Table
```php
Schema::create('payroll_deduction_details', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('payroll_item_id');
    $table->string('name');
    $table->string('type'); // sss, philhealth, pagibig, tax, cash_advance, loan, other
    $table->decimal('amount', 10, 2);
    $table->text('remarks')->nullable();
    $table->timestamps();

    $table->foreign('payroll_item_id')->references('id')->on('payroll_items');
});
```

### 4. Payroll Earning Details Table
```php
Schema::create('payroll_earning_details', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('payroll_item_id');
    $table->string('name');
    $table->string('type'); // basic, overtime, holiday, night_diff, allowance, bonus, commission
    $table->decimal('hours', 8, 2)->nullable();
    $table->decimal('rate', 10, 2)->nullable();
    $table->decimal('amount', 12, 2);
    $table->text('remarks')->nullable();
    $table->timestamps();

    $table->foreign('payroll_item_id')->references('id')->on('payroll_items');
});
```

### 5. Cash Advances Table
```php
Schema::create('cash_advances', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->unsignedBigInteger('user_id'); // Business owner
    $table->unsignedBigInteger('employee_id');
    $table->decimal('amount', 12, 2);
    $table->decimal('balance', 12, 2); // Remaining balance
    $table->date('date');
    $table->string('reason')->nullable();
    $table->enum('status', ['pending', 'approved', 'rejected', 'paid']);
    $table->unsignedBigInteger('approved_by')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 6. Cash Advance Payments Table
```php
Schema::create('cash_advance_payments', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('cash_advance_id');
    $table->unsignedBigInteger('payroll_item_id')->nullable();
    $table->decimal('amount', 12, 2);
    $table->date('date');
    $table->text('remarks')->nullable();
    $table->timestamps();

    $table->foreign('cash_advance_id')->references('id')->on('cash_advances');
    $table->foreign('payroll_item_id')->references('id')->on('payroll_items');
});
```

### 7. Employee Rate History Table
```php
Schema::create('employee_rate_histories', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('employee_id');
    $table->decimal('previous_rate', 10, 2);
    $table->decimal('new_rate', 10, 2);
    $table->enum('rate_type', ['hourly', 'daily', 'monthly']);
    $table->date('effective_date');
    $table->string('reason')->nullable();
    $table->unsignedBigInteger('changed_by');
    $table->timestamps();

    $table->foreign('employee_id')->references('id')->on('users');
});
```

---

## Models

### PayrollPeriod Model
```php
class PayrollPeriod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'start_date', 'end_date', 'pay_date',
        'frequency', 'status', 'total_gross', 'total_deductions',
        'total_net', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'pay_date' => 'date',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function items(): HasMany;
    public function approver(): BelongsTo;

    // Scopes
    public function scopeDraft($query);
    public function scopeApproved($query);
    public function scopePaid($query);

    // Methods
    public function calculateTotals(): void;
    public function approve(User $approver): void;
    public function markAsPaid(): void;
}
```

### PayrollItem Model
```php
class PayrollItem extends Model
{
    use HasFactory, SoftDeletes;

    // Relationships
    public function period(): BelongsTo;
    public function employee(): BelongsTo;
    public function store(): BelongsTo;
    public function deductionDetails(): HasMany;
    public function earningDetails(): HasMany;
    public function bankTransaction(): BelongsTo;

    // Methods
    public function calculateFromAttendance(): void;
    public function calculateGrossPay(): void;
    public function calculateDeductions(): void;
    public function calculateNetPay(): void;
    public function generatePayslip(): array;
}
```

---

## Controllers

### PayrollPeriodController
```
GET    /admin/payroll                    - index (list all periods)
GET    /admin/payroll/create             - create form
POST   /admin/payroll                    - store new period
GET    /admin/payroll/{period}           - show period with all items
GET    /admin/payroll/{period}/edit      - edit period
PUT    /admin/payroll/{period}           - update period
DELETE /admin/payroll/{period}           - delete/cancel period
POST   /admin/payroll/{period}/generate  - generate payroll items from attendance
POST   /admin/payroll/{period}/approve   - approve payroll
POST   /admin/payroll/{period}/pay       - process payment
GET    /admin/payroll/{period}/export    - export to Excel/PDF
```

### PayrollItemController
```
GET    /admin/payroll/{period}/items/{item}      - show payslip
GET    /admin/payroll/{period}/items/{item}/edit - edit payslip
PUT    /admin/payroll/{period}/items/{item}      - update payslip
POST   /admin/payroll/{period}/items/{item}/pay  - pay individual
GET    /admin/payroll/{period}/items/{item}/pdf  - download payslip PDF
```

### CashAdvanceController
```
GET    /admin/cash-advances              - index
GET    /admin/cash-advances/create       - create form
POST   /admin/cash-advances              - store
GET    /admin/cash-advances/{id}         - show with payment history
PUT    /admin/cash-advances/{id}/approve - approve
PUT    /admin/cash-advances/{id}/reject  - reject
POST   /admin/cash-advances/{id}/pay     - record payment
```

---

## Services

### PayrollCalculationService
```php
class PayrollCalculationService
{
    // Calculate basic pay from attendance
    public function calculateBasicPay(User $employee, Carbon $startDate, Carbon $endDate): array;

    // Calculate overtime (hours > 8 per day, or work on rest day)
    public function calculateOvertime(Collection $attendanceRecords, float $hourlyRate): array;

    // Calculate holiday pay
    public function calculateHolidayPay(Collection $attendanceRecords, array $holidays): array;

    // Calculate night differential (10pm - 6am)
    public function calculateNightDiff(Collection $attendanceRecords, float $hourlyRate): array;

    // Calculate government deductions
    public function calculateSSS(float $grossPay): float;
    public function calculatePhilHealth(float $grossPay): float;
    public function calculatePagIbig(float $grossPay): float;
    public function calculateWithholdingTax(float $grossPay, array $deductions): float;

    // Get pending cash advances for deduction
    public function getPendingCashAdvances(User $employee): Collection;
}
```

### PayslipGeneratorService
```php
class PayslipGeneratorService
{
    public function generatePDF(PayrollItem $item): string;
    public function generateBulkPDF(PayrollPeriod $period): string;
    public function sendPayslipEmail(PayrollItem $item): void;
}
```

---

## Workflow

### 1. Create Payroll Period
```
Admin creates payroll period:
- Select date range (start_date, end_date)
- Select pay date
- Select frequency (semi-monthly, monthly, etc.)
- Status: draft
```

### 2. Generate Payroll Items
```
System auto-generates payroll items for each active employee:
1. Fetch all employees under the business
2. For each employee:
   a. Get attendance records within date range
   b. Calculate regular hours, overtime, absences
   c. Apply hourly/daily rate
   d. Calculate gross pay
   e. Calculate deductions (SSS, PhilHealth, Pag-IBIG, Tax)
   f. Deduct cash advances if any
   g. Calculate net pay
   h. Create PayrollItem record
```

### 3. Review & Adjust
```
Admin can review each payslip and manually adjust:
- Add bonuses/allowances
- Add/remove deductions
- Update hours (if attendance was incorrect)
- Add remarks
```

### 4. Approve Payroll
```
1. Admin approves payroll period
2. System locks all payroll items (no more edits)
3. Status changes to 'approved'
4. Records approver and timestamp
```

### 5. Process Payment
```
Option A: Individual Payment
- Pay one employee at a time
- Create bank transaction
- Update payroll item status to 'paid'

Option B: Bulk Payment
- Select bank account
- Process all approved items
- Create bank transactions for each
- Update all statuses to 'paid'
```

---

## Philippine Government Deductions (2026 Rates)

### SSS Contribution Table
```php
// Monthly Salary Credit based on compensation
$sssTable = [
    ['min' => 0, 'max' => 4250, 'ee' => 180, 'er' => 390],
    ['min' => 4250, 'max' => 4750, 'ee' => 202.50, 'er' => 437.50],
    // ... more brackets
    ['min' => 29750, 'max' => 999999, 'ee' => 1350, 'er' => 2920],
];
```

### PhilHealth Contribution
```php
// 5% of basic monthly salary (split 50-50 between employer and employee)
// Minimum: ₱500/month | Maximum: ₱5,000/month
$philhealthRate = 0.05;
$philhealthMin = 500;
$philhealthMax = 5000;
```

### Pag-IBIG Contribution
```php
// Employee: 2% of basic salary (max ₱100)
// Employer: 2% of basic salary (max ₱100)
$pagibigRate = 0.02;
$pagibigMax = 100;
```

### Withholding Tax (BIR Tax Table)
```php
// Monthly tax table
$taxTable = [
    ['min' => 0, 'max' => 20833, 'base' => 0, 'rate' => 0],
    ['min' => 20833, 'max' => 33333, 'base' => 0, 'rate' => 0.15],
    ['min' => 33333, 'max' => 66667, 'base' => 1875, 'rate' => 0.20],
    ['min' => 66667, 'max' => 166667, 'base' => 8541.67, 'rate' => 0.25],
    ['min' => 166667, 'max' => 666667, 'base' => 33541.67, 'rate' => 0.30],
    ['min' => 666667, 'max' => 999999999, 'base' => 183541.67, 'rate' => 0.35],
];
```

---

## Views

### Payroll Period Views
```
resources/views/admin/payroll/
├── index.blade.php          # List all payroll periods
├── create.blade.php         # Create new period
├── show.blade.php           # View period with all employees
├── edit.blade.php           # Edit period details
└── _table.blade.php         # AJAX table partial
```

### Payslip Views
```
resources/views/admin/payroll/items/
├── show.blade.php           # Individual payslip view
├── edit.blade.php           # Edit individual payslip
└── payslip-pdf.blade.php    # PDF template
```

### Cash Advance Views
```
resources/views/admin/cash-advances/
├── index.blade.php
├── create.blade.php
├── show.blade.php
└── _table.blade.php
```

---

## Role Permissions

Add to roles table:
```php
'payroll' => boolean,           // Access payroll module
'payroll_create' => boolean,    // Create payroll periods
'payroll_approve' => boolean,   // Approve payroll
'payroll_pay' => boolean,       // Process payments
'cash_advance' => boolean,      // Manage cash advances
'cash_advance_approve' => boolean, // Approve cash advances
```

---

## Reports

### Available Reports
1. **Payroll Summary** - Total payroll by period
2. **Employee Payroll History** - Individual employee's payroll over time
3. **Deductions Summary** - Breakdown of all deductions
4. **Government Remittances** - SSS, PhilHealth, Pag-IBIG totals for remittance
5. **Cash Advance Report** - Outstanding cash advances

---

## API Endpoints (Mobile)

```
GET  /api/v1/mobile/payroll/current          # Current payroll period
GET  /api/v1/mobile/payroll/history          # Employee's payroll history
GET  /api/v1/mobile/payroll/{id}/payslip     # Download payslip PDF
GET  /api/v1/mobile/cash-advances            # Employee's cash advances
POST /api/v1/mobile/cash-advances            # Request cash advance
```

---

## Implementation Phases

### Phase 1: Core Setup (Week 1)
- [ ] Create migrations
- [ ] Create models with relationships
- [ ] Create PayrollCalculationService
- [ ] Create basic controller structure

### Phase 2: Period Management (Week 2)
- [ ] Payroll period CRUD
- [ ] Auto-generate payroll items from attendance
- [ ] Basic payslip view/edit

### Phase 3: Calculations (Week 3)
- [ ] Government deductions (SSS, PhilHealth, Pag-IBIG)
- [ ] Tax calculation
- [ ] Overtime and holiday pay
- [ ] Night differential

### Phase 4: Cash Advances (Week 4)
- [ ] Cash advance CRUD
- [ ] Approval workflow
- [ ] Auto-deduction in payroll

### Phase 5: Payment Processing (Week 5)
- [ ] Bank integration for payments
- [ ] Bulk payment processing
- [ ] Payment history/audit

### Phase 6: Reports & Export (Week 6)
- [ ] Payslip PDF generation
- [ ] Excel/CSV export
- [ ] Government remittance reports
- [ ] Audit logs

### Phase 7: Mobile API (Week 7)
- [ ] Employee payroll history API
- [ ] Payslip download API
- [ ] Cash advance request API

### Phase 8: Testing & Polish (Week 8)
- [ ] Feature tests
- [ ] UI/UX improvements
- [ ] Documentation

---

## Notes

1. **Rate Configuration**: User model already has `rate` field - need to add `rate_type` (hourly/daily/monthly)
2. **Attendance Integration**: Payroll pulls from `attendance_records` table
3. **Banking Integration**: Payments create `bank_transactions` records
4. **Audit Trail**: All changes logged via existing `audit_logs` system
5. **Multi-store**: Employees can be assigned to stores, payroll can be filtered by store

# Sales Report Exclusion System - Dashboard Specification

## 3.0 Overview

This document specifies the implementation for the **apex_dashboard** (Rolworks POS Mobile Back Office) Flutter application. The dashboard displays reports and readings, and must respect the Sales Report Exclusion settings when displaying data.

**Component:** `apex_dashboard` (Rolworks POS Mobile Back Office)  
**Technology:** Flutter, Dart  
**Estimated Effort:** 6-8 hours

---

## 3.1 Key Principles

### 3.1.1 Display Respects Settings
- Reports and readings should display filtered data when exclusion is enabled
- A toggle should allow users to switch between filtered and original views
- The toggle state should persist during the session

### 3.1.2 No Permanent Data Modification
- The dashboard does NOT modify data
- All filtering is applied at display time
- Original data is always preserved in the backend

### 3.1.3 Consistency Across Views
- All reports should respect the same filtering rules
- All readings should respect the same filtering rules
- The toggle should affect all views consistently

---

## 3.2 API Integration

### 3.2.1 Modify Report Service

Update the report service to support the `show_original` parameter.

**File:** `lib/services/report_service.dart`

```dart
import 'package:apex_pos/services/api_services.dart';

class ReportService {
  /// Get sales report with Sales Report Exclusion support
  /// 
  /// The [showOriginal] parameter controls whether to show filtered or original data.
  /// - If [showOriginal] is true: Returns original, unfiltered data
  /// - If [showOriginal] is false: Returns filtered data (if exclusion is enabled)
  static Future<Map<String, dynamic>> getSalesReport({
    required DateTime startDate,
    required DateTime endDate,
    int? storeId,
    bool showOriginal = false,
  }) async {
    final queryParams = <String, dynamic>{
      'start_date': startDate.toIso8601String(),
      'end_date': endDate.toIso8601String(),
      if (storeId != null) 'store_id': storeId.toString(),
      'show_original': showOriginal.toString(),
    };

    return await ApiServices.getRequest(
      '/reports/sales',
      queryParams,
    );
  }

  /// Get daily sales summary with Sales Report Exclusion support
  static Future<Map<String, dynamic>> getDailySalesSummary({
    required DateTime date,
    int? storeId,
    bool showOriginal = false,
  }) async {
    final queryParams = <String, dynamic>{
      'date': date.toIso8601String(),
      if (storeId != null) 'store_id': storeId.toString(),
      'show_original': showOriginal.toString(),
    };

    return await ApiServices.getRequest(
      '/reports/daily-summary',
      queryParams,
    );
  }

  /// Get items report with Sales Report Exclusion support
  static Future<Map<String, dynamic>> getItemsReport({
    required DateTime startDate,
    required DateTime endDate,
    int? storeId,
    bool showOriginal = false,
  }) async {
    final queryParams = <String, dynamic>{
      'start_date': startDate.toIso8601String(),
      'end_date': endDate.toIso8601String(),
      if (storeId != null) 'store_id': storeId.toString(),
      'show_original': showOriginal.toString(),
    };

    return await ApiServices.getRequest(
      '/reports/items',
      queryParams,
    );
  }
}
```

---

### 3.2.2 Modify Reading Service

Update the reading service to support the `show_original` parameter.

**File:** `lib/services/reading_service.dart`

```dart
import 'package:apex_pos/services/api_services.dart';

class ReadingService {
  /// Get Z-reading with Sales Report Exclusion support
  static Future<Map<String, dynamic>> getZReading(
    int readingId, {
    bool showOriginal = false,
  }) async {
    return await ApiServices.getRequest(
      '/zreadings/$readingId',
      {'show_original': showOriginal.toString()},
    );
  }

  /// Get X-reading with Sales Report Exclusion support
  static Future<Map<String, dynamic>> getXReading(
    int readingId, {
    bool showOriginal = false,
  }) async {
    return await ApiServices.getRequest(
      '/xreadings/$readingId',
      {'show_original': showOriginal.toString()},
    );
  }

  /// List Z-readings with Sales Report Exclusion support
  static Future<Map<String, dynamic>> listZReadings({
    required DateTime startDate,
    required DateTime endDate,
    int? storeId,
    bool showOriginal = false,
  }) async {
    final queryParams = <String, dynamic>{
      'start_date': startDate.toIso8601String(),
      'end_date': endDate.toIso8601String(),
      if (storeId != null) 'store_id': storeId.toString(),
      'show_original': showOriginal.toString(),
    };

    return await ApiServices.getRequest(
      '/zreadings',
      queryParams,
    );
  }

  /// List X-readings with Sales Report Exclusion support
  static Future<Map<String, dynamic>> listXReadings({
    required DateTime startDate,
    required DateTime endDate,
    int? storeId,
    bool showOriginal = false,
  }) async {
    final queryParams = <String, dynamic>{
      'start_date': startDate.toIso8601String(),
      'end_date': endDate.toIso8601String(),
      if (storeId != null) 'store_id': storeId.toString(),
      'show_original': showOriginal.toString(),
    };

    return await ApiServices.getRequest(
      '/xreadings',
      queryParams,
    );
  }
}
```

---

## 3.3 State Management

### 3.3.1 ShowOriginalProvider

Create a provider to manage the show_original toggle state across the app.

**File:** `lib/providers/show_original_provider.dart`

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Provider for show_original toggle state
final showOriginalProvider = StateNotifierProvider<
    ShowOriginalNotifier,
    bool
>((ref) {
  return ShowOriginalNotifier();
});

/// Notifier for managing show_original state
class ShowOriginalNotifier extends StateNotifier<bool> {
  static const String _prefsKey = 'sales_report_exclusion_show_original';
  
  ShowOriginalNotifier() : super(false);

  /// Initialize from shared preferences
  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getBool(_prefsKey);
    if (saved != null) {
      state = saved;
    }
  }

  /// Toggle the show_original setting
  Future<void> toggle() async {
    state = !state;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_prefsKey, state);
  }

  /// Set the show_original setting
  Future<void> set(bool value) async {
    if (state != value) {
      state = value;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_prefsKey, value);
    }
  }

  /// Reset to default (false - show filtered data)
  Future<void> reset() async {
    state = false;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_prefsKey, false);
  }
}
```

---

## 3.4 UI Components

### 3.4.1 ShowOriginalToggle Widget

Create a reusable toggle widget that can be added to report and reading pages.

**File:** `lib/components/show_original_toggle.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:apex_pos/providers/show_original_provider.dart';

class ShowOriginalToggle extends ConsumerWidget {
  const ShowOriginalToggle({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final showOriginal = ref.watch(showOriginalProvider);
    final notifier = ref.read(showOriginalProvider.notifier);

    return Row(
      children: [
        Switch(
          value: showOriginal,
          onChanged: (value) => notifier.toggle(),
          activeColor: AppColor.success,
          inactiveThumbColor: AppColor.warning,
        ),
        const SizedBox(width: 8),
        Text(
          showOriginal ? 'Original' : 'Sales Report Adjusted',
          style: TextStyle(
            color: showOriginal ? AppColor.success : AppColor.warning,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(width: 8),
        IconButton(
          icon: const Icon(Icons.info_outline, size: 18),
          onPressed: () => _showTooltip(context),
          tooltip: 'Toggle between original and Sales Report-adjusted data',
        ),
      ],
    );
  }

  void _showTooltip(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('View Mode'),
        content: const Text(
          'Original: Shows all data as recorded\n'
          'Sales Report Adjusted: Shows data with excluded items removed (for tax reporting)',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }
}
```

---

## 3.5 Report Pages

### 3.5.1 Sales Report Page

Modify the sales report page to include the toggle and pass the show_original parameter.

**File:** `lib/pages/reports/sales_report_page.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:apex_pos/services/report_service.dart';
import 'package:apex_pos/providers/show_original_provider.dart';
import 'package:apex_pos/components/show_original_toggle.dart';

class SalesReportPage extends ConsumerStatefulWidget {
  const SalesReportPage({super.key});

  @override
  ConsumerState<SalesReportPage> createState() => _SalesReportPageState();
}

class _SalesReportPageState extends ConsumerState<SalesReportPage> {
  DateTimeRange dateRange = DateTimeRange(
    start: DateTime.now().subtract(const Duration(days: 7)),
    end: DateTime.now(),
  );

  @override
  void initState() {
    super.initState();
    // Initialize show_original provider
    ref.read(showOriginalProvider.notifier).init();
  }

  @override
  Widget build(BuildContext context) {
    final showOriginal = ref.watch(showOriginalProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Sales Report'),
        actions: [
          ShowOriginalToggle(),
          const SizedBox(width: 16),
        ],
      ),
      body: Column(
        children: [
          // Date picker
          Card(
            margin: const EdgeInsets.all(16),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Date Range',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  DateRangePicker(
                    dateRange: dateRange,
                    onChanged: (range) => setState(() => dateRange = range),
                  ),
                ],
              ),
            ),
          ),

          // Report data
          Expanded(
            child: FutureBuilder<Map<String, dynamic>>(
              future: ReportService.getSalesReport(
                startDate: dateRange.start,
                endDate: dateRange.end,
                showOriginal: showOriginal,
              ),
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (snapshot.hasError || !snapshot.hasData) {
                  return const Center(child: Text('Error loading report'));
                }

                final reportData = snapshot.data!;
                return SalesReportContent(data: reportData);
              },
            ),
          ),
        ],
      ),
    );
  }
}

class SalesReportContent extends StatelessWidget {
  final Map<String, dynamic> data;

  const SalesReportContent({super.key, required this.data});

  @override
  Widget build(BuildContext context) {
    // Build your report UI from data
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Summary cards
          ReportSummaryCards(data: data),
          const SizedBox(height: 16),
          
          // Detailed table
          ReportSalesTable(data: data),
        ],
      ),
    );
  }
}
```

---

### 3.5.2 Report Summary Cards

```dart
class ReportSummaryCards extends StatelessWidget {
  final Map<String, dynamic> data;

  const ReportSummaryCards({super.key, required this.data});

  @override
  Widget build(BuildContext context) {
    final totalSales = data['total_sales'] ?? 0.0;
    final totalVat = data['total_vat'] ?? 0.0;
    final totalExempt = data['total_exempt'] ?? 0.0;
    final grandTotal = data['grand_total'] ?? 0.0;
    final transactionCount = data['transaction_count'] ?? 0;

    // Check if data is filtered
    final isFiltered = data['is_filtered'] ?? false;

    return Row(
      children: [
        Expanded(
          child: SummaryCard(
            title: 'Total Sales',
            value: 'P ${totalSales.toStringAsFixed(2)}',
            subtitle: 'VATable + Exempt',
            icon: Icons.shopping_cart,
            color: AppColor.info,
          ),
        ),
        Expanded(
          child: SummaryCard(
            title: 'VAT',
            value: 'P ${totalVat.toStringAsFixed(2)}',
            subtitle: '12% VAT',
            icon: Icons.receipt,
            color: AppColor.success,
          ),
        ),
        Expanded(
          child: SummaryCard(
            title: 'Transactions',
            value: transactionCount.toString(),
            subtitle: 'Total receipts',
            icon: Icons.receipt_long,
            color: AppColor.warning,
          ),
        ),
      ],
    );
  }
}

class SummaryCard extends StatelessWidget {
  final String title;
  final String value;
  final String subtitle;
  final IconData icon;
  final Color color;

  const SummaryCard({
    super.key,
    required this.title,
    required this.value,
    required this.subtitle,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.all(4),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color, size: 24),
            const SizedBox(height: 8),
            Text(
              value,
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              title,
              style: TextStyle(
                fontSize: 12,
                color: Colors.grey[600],
              ),
            ),
            Text(
              subtitle,
              style: TextStyle(
                fontSize: 10,
                color: Colors.grey[500],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
```

---

## 3.6 Reading Pages

### 3.6.1 Z-Reading Page

Modify the Z-reading page to include the toggle.

**File:** `lib/pages/readings/zreading_page.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:apex_pos/services/reading_service.dart';
import 'package:apex_pos/providers/show_original_provider.dart';
import 'package:apex_pos/components/show_original_toggle.dart';

class ZReadingPage extends ConsumerStatefulWidget {
  final int readingId;

  const ZReadingPage({super.key, required this.readingId});

  @override
  ConsumerState<ZReadingPage> createState() => _ZReadingPageState();
}

class _ZReadingPageState extends ConsumerState<ZReadingPage> {
  @override
  void initState() {
    super.initState();
    // Initialize show_original provider
    ref.read(showOriginalProvider.notifier).init();
  }

  @override
  Widget build(BuildContext context) {
    final showOriginal = ref.watch(showOriginalProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Z-Reading Details'),
        actions: [
          ShowOriginalToggle(),
          const SizedBox(width: 16),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: ReadingService.getZReading(
          widget.readingId,
          showOriginal: showOriginal,
        ),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError || !snapshot.hasData) {
            return const Center(child: Text('Error loading reading'));
          }

          final readingData = snapshot.data!;
          return ZReadingContent(data: readingData);
        },
      ),
    );
  }
}

class ZReadingContent extends StatelessWidget {
  final Map<String, dynamic> data;

  const ZReadingContent({super.key, required this.data});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Reading header
          ZReadingHeader(data: data),
          const SizedBox(height: 16),
          
          // Summary
          ZReadingSummary(data: data),
          const SizedBox(height: 16),
          
          // Associated sales
          ZReadingSalesList(data: data),
        ],
      ),
    );
  }
}

class ZReadingHeader extends StatelessWidget {
  final Map<String, dynamic> data;

  const ZReadingHeader({super.key, required this.data});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Z-Reading #${data['id']}',
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        data['pos']['name'] ?? 'N/A',
                        style: TextStyle(
                          fontSize: 14,
                          color: Colors.grey[600],
                        ),
                      ),
                    ],
                  ),
                ),
                if (data['is_filtered'] == true)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: AppColor.warning.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      'Sales Report Adjusted',
                      style: TextStyle(
                        color: AppColor.warning,
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Divider(color: Colors.grey[300]),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: _buildInfoRow(
                    Icons.calendar_today,
                    'Date',
                    data['created_at'] ?? 'N/A',
                  ),
                ),
                Expanded(
                  child: _buildInfoRow(
                    Icons.store,
                    'Store',
                    data['store']['name'] ?? 'N/A',
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, size: 16, color: Colors.grey[500]),
        const SizedBox(width: 8),
        Text(
          '$label: ',
          style: TextStyle(
            fontSize: 12,
            color: Colors.grey[600],
          ),
        ),
        Text(
          value,
          style: const TextStyle(fontSize: 12),
        ),
      ],
    );
  }
}

class ZReadingSummary extends StatelessWidget {
  final Map<String, dynamic> data;

  const ZReadingSummary({super.key, required this.data});

  @override
  Widget build(BuildContext context) {
    final grossSales = data['gross_sales'] ?? 0.0;
    final vatableSales = data['vatable_sales'] ?? 0.0;
    final vat = data['vat'] ?? 0.0;
    final vatExempt = data['vat_exempt'] ?? 0.0;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Summary',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Divider(color: Colors.grey[300]),
            const SizedBox(height: 8),
            _buildSummaryRow('Gross Sales', 'P ${grossSales.toStringAsFixed(2)}'),
            const SizedBox(height: 4),
            _buildSummaryRow('VATable Sales', 'P ${vatableSales.toStringAsFixed(2)}'),
            const SizedBox(height: 4),
            _buildSummaryRow('VAT (12%)', 'P ${vat.toStringAsFixed(2)}'),
            const SizedBox(height: 4),
            _buildSummaryRow('VAT Exempt', 'P ${vatExempt.toStringAsFixed(2)}'),
          ],
        ),
      ),
    );
  }

  Widget _buildSummaryRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(color: Colors.grey[600]),
        ),
        Text(
          value,
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
      ],
    );
  }
}
```

---

### 3.6.2 X-Reading Page

Similarly, modify the X-reading page (code structure is the same as Z-reading).

---

### 3.6.3 Reading List Page

**File:** `lib/pages/readings/readings_page.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:apex_pos/services/reading_service.dart';
import 'package:apex_pos/providers/show_original_provider.dart';
import 'package:apex_pos/components/show_original_toggle.dart';

class ReadingsPage extends ConsumerStatefulWidget {
  const ReadingsPage({super.key});

  @override
  ConsumerState<ReadingsPage> createState() => _ReadingsPageState();
}

class _ReadingsPageState extends ConsumerState<ReadingsPage> {
  DateTimeRange dateRange = DateTimeRange(
    start: DateTime.now().subtract(const Duration(days: 7)),
    end: DateTime.now(),
  );
  int? selectedStoreId;

  @override
  void initState() {
    super.initState();
    ref.read(showOriginalProvider.notifier).init();
  }

  @override
  Widget build(BuildContext context) {
    final showOriginal = ref.watch(showOriginalProvider);

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Readings'),
          actions: [
            ShowOriginalToggle(),
            const SizedBox(width: 16),
          ],
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Z-Readings'),
              Tab(text: 'X-Readings'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            // Z-Readings Tab
            _buildReadingsTab(true, showOriginal),
            // X-Readings Tab
            _buildReadingsTab(false, showOriginal),
          ],
        ),
      ),
    );
  }

  Widget _buildReadingsTab(bool isZReading, bool showOriginal) {
    return Column(
      children: [
        // Date and store filter
        Card(
          margin: const EdgeInsets.all(16),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                DateRangePicker(
                  dateRange: dateRange,
                  onChanged: (range) => setState(() => dateRange = range),
                ),
                const SizedBox(height: 8),
                StoreDropdown(
                  selectedId: selectedStoreId,
                  onChanged: (id) => setState(() => selectedStoreId = id),
                ),
              ],
            ),
          ),
        ),

        // Readings list
        Expanded(
          child: FutureBuilder<Map<String, dynamic>>(
            future: isZReading
                ? ReadingService.listZReadings(
                    startDate: dateRange.start,
                    endDate: dateRange.end,
                    storeId: selectedStoreId,
                    showOriginal: showOriginal,
                  )
                : ReadingService.listXReadings(
                    startDate: dateRange.start,
                    endDate: dateRange.end,
                    storeId: selectedStoreId,
                    showOriginal: showOriginal,
                  ),
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }

              if (snapshot.hasError || !snapshot.hasData) {
                return const Center(child: Text('Error loading readings'));
              }

              final readings = List<Map<String, dynamic>>.from(
                snapshot.data!['data'] ?? [],
              );
              
              if (readings.isEmpty) {
                return const Center(
                  child: Text('No readings found for selected period'),
                );
              }

              return ListView.builder(
                itemCount: readings.length,
                itemBuilder: (context, index) {
                  final reading = readings[index];
                  return ReadingCard(
                    reading: reading,
                    isZReading: isZReading,
                  );
                },
              );
            },
          ),
        ),
      ],
    );
  }
}

class ReadingCard extends StatelessWidget {
  final Map<String, dynamic> reading;
  final bool isZReading;

  const ReadingCard({
    super.key,
    required this.reading,
    required this.isZReading,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: InkWell(
        onTap: () => _navigateToDetail(context),
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    isZReading ? 'Z-Reading' : 'X-Reading',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    '#${reading['id']}',
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey[600],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      reading['pos']['name'] ?? 'N/A',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey[600],
                      ),
                    ),
                  ),
                  if (reading['is_filtered'] == true)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 4,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: AppColor.warning.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        'Filtered',
                        style: TextStyle(
                          color: AppColor.warning,
                          fontSize: 10,
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    reading['created_at'] ?? 'N/A',
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey[600],
                    ),
                  ),
                  Text(
                    'P ${(reading['gross_sales'] ?? 0).toStringAsFixed(2)}',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _navigateToDetail(BuildContext context) {
    if (isZReading) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (context) => ZReadingPage(readingId: reading['id']),
        ),
      );
    } else {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (context) => XReadingPage(readingId: reading['id']),
        ),
      );
    }
  }
}
```

---

## 3.7 Backend API Requirements

The dashboard app depends on the backend implementing the following API changes:

1. All report endpoints should accept a `show_original` query parameter
2. All reading endpoints should accept a `show_original` query parameter
3. Responses should include an `is_filtered` flag indicating if data was filtered

These requirements are specified in the [Backend Specification](10_BACKEND_SPEC.md).

---

## 3.8 File Changes Summary

| File | Action | Description |
|------|--------|-------------|
| `lib/services/report_service.dart` | Modify | Add show_original parameter support |
| `lib/services/reading_service.dart` | Modify | Add show_original parameter support |
| `lib/providers/show_original_provider.dart` | New | State management for show_original toggle |
| `lib/components/show_original_toggle.dart` | New | Reusable toggle widget |
| `lib/pages/reports/sales_report_page.dart` | Modify | Add toggle, pass show_original parameter |
| `lib/pages/readings/zreading_page.dart` | Modify | Add toggle, pass show_original parameter |
| `lib/pages/readings/readings_page.dart` | Modify | Add toggle, pass show_original parameter |
| Other report/reading pages | Modify | Add toggle as needed |

**Total Files:** 8-10 files
**Estimated Effort:** 6-8 hours

---

## 3.9 Acceptance Criteria

### 3.9.1 Must Have

- [ ] Reports accept show_original parameter
- [ ] Readings accept show_original parameter
- [ ] Toggle widget implemented and working
- [ ] Settings persist during session
- [ ] All reports respect the show_original setting
- [ ] All readings respect the show_original setting

### 3.9.2 Should Have

- [ ] Settings persist across app restarts (using SharedPreferences)
- [ ] Visual indicator when viewing filtered data
- [ ] Tooltip explaining the toggle

### 3.9.3 Nice to Have

- [ ] Global settings page with show_original toggle
- [ ] Per-report override of show_original setting
- [ ] Export filtered vs original reports separately

---

## 3.10 Testing Checklist

- [ ] View report with exclusion enabled and show_original=false - verify filtered data
- [ ] View report with exclusion enabled and show_original=true - verify original data
- [ ] View report with exclusion disabled - verify all data shown
- [ ] Toggle show_original - verify data updates correctly
- [ ] View Z-reading with exclusion enabled - verify filtered data
- [ ] View X-reading with exclusion enabled - verify filtered data
- [ ] Toggle settings persist across page navigation
- [ ] Toggle settings persist across app restart

---

## 3.11 Dependencies

The dashboard app depends on:

1. Backend implementing show_original parameter support (see [Backend Spec](10_BACKEND_SPEC.md))
2. POS app capturing and storing data correctly
3. Shared preferences for persistence (already in pubspec.yaml)

---

## 3.12 Notes

- The dashboard does NOT need to know which items are excluded - it just displays what the backend returns
- The toggle is a client-side preference, not a backend setting
- When show_original=true, the backend returns unfiltered data
- When show_original=false, the backend applies filtering based on the current Sales Report Exclusion settings
- The backend always preserves original data - filtering is applied at query time

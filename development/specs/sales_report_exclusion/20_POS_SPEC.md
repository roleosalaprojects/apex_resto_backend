# Sales Report Exclusion System - POS Terminal Specification

## 2.0 Overview

This document specifies the implementation for the **apex_pos** Flutter application. The POS terminal is responsible for capturing sales data and displaying receipts, but **all filtering logic resides in the backend**. The POS app must:

1. Send complete sale data to the backend (no filtering)
2. Display receipts as returned by the backend (filtered or unfiltered)
3. **ALWAYS** print original (unfiltered) receipts
4. Optionally cache and display exclusion settings

**Component:** `apex_pos`  
**Technology:** Flutter, Dart  
**Estimated Effort:** 8-10 hours

---

## 2.1 Key Principles

### 2.1.1 Data Capture
- **ALWAYS** send ALL items in the cart to the backend
- Do NOT filter items before sending
- The backend handles all filtering logic

### 2.1.2 Data Display
- Display receipts as returned by the backend
- The backend may return filtered or unfiltered data based on settings
- Show warning indicator when viewing filtered data (optional)

### 2.1.3 Printing
- **CRITICAL:** Printing must ALWAYS use original data
- Call a dedicated print endpoint that returns unfiltered data
- Never filter items when printing

---

## 2.2 API Integration

### 2.2.1 New API Methods

Add the following methods to `lib/services/api_services.dart`:

```dart
import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiServices {
  // ... existing code

  /// Check if an item is excluded from Sales Report reporting
  /// 
  /// Parameters:
  ///   - itemId: The ID of the item to check
  ///   - storeId: The ID of the store
  /// 
  /// Returns: Map containing success, is_excluded, and item details
  static Future<Map<String, dynamic>> checkSalesReportExclusion({
    required int itemId,
    required int storeId,
  }) async {
    try {
      final uri = Uri.http(
        _host,
        '$_apiSuffix/sales-report-exclusion/check/$itemId',
        {'store_id': storeId.toString()},
      );
      final response = await _client.get(uri, headers: headers).timeout(_timeout);
      
      if (response.statusCode >= 200 && response.statusCode < 300) {
        return jsonDecode(response.body);
      } else {
        return {
          'success': false,
          'message': 'Request failed: ${response.statusCode}',
          'is_excluded': false
        };
      }
    } on TimeoutException {
      return {
        'success': false,
        'message': 'Request timed out',
        'is_excluded': false
      };
    } catch (e) {
      return {
        'success': false,
        'message': e.toString(),
        'is_excluded': false
      };
    }
  }

  /// Get current Sales Report Exclusion settings
  /// 
  /// Returns: Map containing exclusion_enabled and show_original flags
  static Future<Map<String, dynamic>> getSalesReportExclusionSettings() async {
    try {
      final uri = Uri.http(_host, '$_apiSuffix/sales-report-exclusion/settings');
      final response = await _client.get(uri, headers: headers).timeout(_timeout);
      
      if (response.statusCode >= 200 && response.statusCode < 300) {
        return jsonDecode(response.body);
      } else {
        return {
          'success': false,
          'message': 'Request failed: ${response.statusCode}',
          'exclusion_enabled': false,
          'show_original': false,
        };
      }
    } on TimeoutException {
      return {
        'success': false,
        'message': 'Request timed out',
        'exclusion_enabled': false,
        'show_original': false,
      };
    } catch (e) {
      return {
        'success': false,
        'message': e.toString(),
        'exclusion_enabled': false,
        'show_original': false,
      };
    }
  }

  /// Get sale for display (may be filtered based on backend settings)
  /// 
  /// Parameters:
  ///   - saleId: The ID of the sale to fetch
  /// 
  /// Returns: Map containing sale data (filtered or unfiltered)
  static Future<Map<String, dynamic>> getSaleForDisplay(int saleId) async {
    try {
      final uri = Uri.http(_host, '$_apiSuffix/sales/$saleId');
      final response = await _client.get(uri, headers: headers).timeout(_timeout);
      
      if (response.statusCode >= 200 && response.statusCode < 300) {
        final data = jsonDecode(response.body);
        // The backend determines if this is filtered or original
        return data;
      } else {
        return {
          'success': false,
          'message': 'Request failed: ${response.statusCode}'
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': e.toString()
      };
    }
  }

  /// Get sale for printing (ALWAYS original, unfiltered data)
  /// 
  /// Parameters:
  ///   - saleId: The ID of the sale to print
  /// 
  /// Returns: Map containing original sale data
  static Future<Map<String, dynamic>> getSaleForPrinting(int saleId) async {
    try {
      final uri = Uri.http(_host, '$_apiSuffix/sales/$saleId/print');
      final response = await _client.get(uri, headers: headers).timeout(_timeout);
      
      if (response.statusCode >= 200 && response.statusCode < 300) {
        return jsonDecode(response.body);
      } else {
        return {
          'success': false,
          'message': 'Request failed: ${response.statusCode}'
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': e.toString()
      };
    }
  }
}
```

---

## 2.3 State Management

### 2.3.1 SalesReportExclusionSettings Model

Create a model to hold Sales Report Exclusion settings in the app state.

**File:** `lib/models/sales_report_exclusion_settings.dart`

```dart
/// Model for Sales Report Exclusion settings
class SalesReportExclusionSettings {
  final bool exclusionEnabled;
  final bool showOriginal;
  final DateTime? lastUpdated;

  SalesReportExclusionSettings({
    required this.exclusionEnabled,
    required this.showOriginal,
    this.lastUpdated,
  });

  factory SalesReportExclusionSettings.fromJson(Map<String, dynamic> json) {
    return SalesReportExclusionSettings(
      exclusionEnabled: json['exclusion_enabled'] ?? false,
      showOriginal: json['show_original'] ?? false,
      lastUpdated: json['last_updated'] != null 
          ? DateTime.parse(json['last_updated']) 
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'exclusion_enabled': exclusionEnabled,
      'show_original': showOriginal,
      'last_updated': lastUpdated?.toIso8601String(),
    };
  }

  /// Create default settings (exclusion disabled)
  factory SalesReportExclusionSettings.defaultSettings() {
    return SalesReportExclusionSettings(
      exclusionEnabled: false,
      showOriginal: false,
      lastUpdated: null,
    );
  }

  /// Check if filtering is currently active
  bool get isFilteringActive => exclusionEnabled && !showOriginal;

  SalesReportExclusionSettings copyWith({
    bool? exclusionEnabled,
    bool? showOriginal,
    DateTime? lastUpdated,
  }) {
    return SalesReportExclusionSettings(
      exclusionEnabled: exclusionEnabled ?? this.exclusionEnabled,
      showOriginal: showOriginal ?? this.showOriginal,
      lastUpdated: lastUpdated ?? this.lastUpdated,
    );
  }
}
```

---

### 2.3.2 SalesReportExclusionSettingsProvider

Create a Riverpod provider to manage Sales Report Exclusion settings state.

**File:** `lib/providers/sales_report_exclusion_provider.dart`

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:apex_pos/models/sales_report_exclusion_settings.dart';
import 'package:apex_pos/services/api_services.dart';

/// Provider for Sales Report Exclusion settings
final salesReportExclusionSettingsProvider = StateNotifierProvider<
    SalesReportExclusionSettingsNotifier, 
    SalesReportExclusionSettings
>((ref) {
  return SalesReportExclusionSettingsNotifier();
});

/// Notifier for managing Sales Report Exclusion settings
class SalesReportExclusionSettingsNotifier extends StateNotifier<SalesReportExclusionSettings> {
  SalesReportExclusionSettingsNotifier() 
      : super(SalesReportExclusionSettings.defaultSettings());

  /// Fetch settings from the backend
  Future<void> fetchSettings() async {
    try {
      final response = await ApiServices.getSalesReportExclusionSettings();
      if (response['success'] == true) {
        state = SalesReportExclusionSettings.fromJson(response);
      }
    } catch (e) {
      // Keep current state on error
    }
  }

  /// Refresh settings from the backend
  Future<void> refresh() async {
    await fetchSettings();
  }

  /// Check if an item is excluded (calls backend)
  Future<bool> isItemExcluded(int itemId, int storeId) async {
    try {
      final response = await ApiServices.checkSalesReportExclusion(
        itemId: itemId,
        storeId: storeId,
      );
      return response['is_excluded'] ?? false;
    } catch (e) {
      return false;
    }
  }

  /// Check if filtering is currently active
  bool get isFilteringActive => state.isFilteringActive;

  /// Get current settings
  SalesReportExclusionSettings get currentSettings => state;
}
```

---

## 2.4 Sale Creation Flow

### 2.4.1 NO CHANGES NEEDED to Sale Submission

The sale creation flow **does NOT need to change**. The POS app should continue to send ALL items in the cart to the backend.

```dart
// In your sale submission code (e.g., cart_controller.dart)
Future<Map<String, dynamic>> submitSale({
  required List<Map<String, dynamic>> cartItems,
  required int customerId,
  required int posId,
  required int storeId,
  // ... other parameters
}) async {
  final saleData = {
    'cart_items': cartItems, // ALL items - no filtering
    'customer_id': customerId,
    'pos_id': posId,
    'store_id': storeId,
    // ... other fields
  };

  // Send to backend - backend handles filtering
  final response = await ApiServices.postRequest(
    '/sales',
    saleData,
    context,
  );

  return response;
}
```

---

## 2.5 Receipt Display

### 2.5.1 Fetch and Display Sale

When displaying a sale (e.g., after creation or when viewing a receipt), use the standard API call. The backend will return filtered or unfiltered data based on the current settings.

```dart
// In receipt display page
class ReceiptPage extends ConsumerWidget {
  final int saleId;

  const ReceiptPage({super.key, required this.saleId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settings = ref.watch(salesReportExclusionSettingsProvider);
    
    return Scaffold(
      appBar: AppBar(title: const Text('Receipt')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: ApiServices.getSaleForDisplay(saleId),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          
          if (snapshot.hasError || !snapshot.hasData || snapshot.data!['success'] != true) {
            return Center(
              child: Text(
                snapshot.data?['message'] ?? 'Failed to load receipt',
              ),
            );
          }
          
          final saleData = snapshot.data!;
          
          // Display the sale as returned by backend
          return ReceiptContent(
            sale: saleData,
            isFiltered: settings.isFilteringActive,
          );
        },
      ),
    );
  }
}
```

---

### 2.5.2 ReceiptContent Widget

```dart
class ReceiptContent extends StatelessWidget {
  final Map<String, dynamic> sale;
  final bool isFiltered;

  const ReceiptContent({
    super.key,
    required this.sale,
    this.isFiltered = false,
  });

  @override
  Widget build(BuildContext context) {
    final saleLines = List<Map<String, dynamic>>.from(sale['sale_lines'] ?? []);
    
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Warning indicator when showing filtered data
        if (isFiltered)
          Container(
            padding: const EdgeInsets.all(8),
            color: AppColor.warning.withOpacity(0.2),
            child: Row(
              children: [
                const Icon(Icons.info_outline, color: AppColor.warning),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Displaying Sales Report-adjusted receipt (some items excluded)',
                    style: TextStyle(
                      color: AppColor.warning,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),
          ),
        
        // Sale header
        ReceiptHeader(sale: sale),
        
        // Items list
        Expanded(
          child: ListView.builder(
            itemCount: saleLines.length,
            itemBuilder: (context, index) {
              final line = saleLines[index];
              return ReceiptLineItem(line: line);
            },
          ),
        ),
        
        // Totals
        ReceiptTotals(sale: sale),
        
        // Buttons
        Row(
          children: [
            Expanded(
              child: ElevatedButton.icon(
                onPressed: () => _printReceipt(context, sale['id']),
                icon: const Icon(Icons.print),
                label: const Text('Print'),
              ),
            ),
          ],
        ),
      ],
    );
  }

  void _printReceipt(BuildContext context, int saleId) {
    // Use printing service with original data
    ReceiptPrinter.printReceipt(saleId);
  }
}
```

---

## 2.6 Printing Implementation

### 2.6.1 CRITICAL: Always Use Original Data

Printing must **ALWAYS** use the original, unfiltered sale data. This is the most important requirement to avoid suspicion during Sales Report audits.

```dart
// lib/services/printing_service.dart
import 'package:apex_pos/services/api_services.dart';
import 'package:esc_pos_printer/esc_pos_printer.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';

class ReceiptPrinter {
  /// Print a receipt - ALWAYS uses original data
  /// 
  /// This is CRITICAL for compliance. Even if Sales Report Exclusion is enabled,
  /// printed receipts must show all items to avoid suspicion.
  static Future<void> printReceipt(int saleId) async {
    try {
      // Fetch sale with ORIGINAL data - NEVER filtered
      final response = await ApiServices.getSaleForPrinting(saleId);
      
      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Failed to load sale');
      }
      
      final saleData = response;
      
      // Verify this is original data
      if (saleData['is_original'] != true) {
        throw Exception('Received filtered data for printing - this should never happen');
      }
      
      // Print using the original sale data
      await _printThermalReceipt(saleData);
      
    } catch (e) {
      // Show error to user
      rethrow;
    }
  }

  /// Internal method to print thermal receipt
  static Future<void> _printThermalReceipt(Map<String, dynamic> saleData) async {
    // Use saleData['sale_lines'] which contains ALL items
    final saleLines = List<Map<String, dynamic>>.from(saleData['sale_lines'] ?? []);
    
    // Generate print commands
    final printer = EscPosPrinter();
    final commands = <int>[];
    
    // Add header
    commands.addAll(printer.text('${saleData['store']['name']}', 
        styles: const PosStyles(align: PosAlign.center, bold: true)));
    commands.addAll(printer.text('${saleData['store']['address']}', 
        styles: const PosStyles(align: PosAlign.center)));
    commands.addAll(printer.emptyLines(1));
    
    // Add sale date/time
    commands.addAll(printer.text(
        'Date: ${saleData['created_at']}',
        styles: const PosStyles(align: PosAlign.left)
    ));
    commands.addAll(printer.text(
        'Time: ${saleData['created_at']}',
        styles: const PosStyles(align: PosAlign.left)
    ));
    commands.addAll(printer.emptyLines(1));
    
    // Add items - ALL items, no filtering
    commands.addAll(printer.text('--- ITEMS ---', 
        styles: const PosStyles(align: PosAlign.center, bold: true)));
    
    for (final line in saleLines) {
      final name = line['item']['name'] ?? 'Unknown';
      final qty = line['quantity'] ?? 1;
      final price = line['price'] ?? 0;
      final total = line['total'] ?? 0;
      
      commands.addAll(printer.row([
        PosColumn(text: name, width: 20),
        PosColumn(text: qty.toString(), width: 5, styles: const PosStyles(align: PosAlign.right)),
        PosColumn(text: 'P ${price.toStringAsFixed(2)}', width: 10, styles: const PosStyles(align: PosAlign.right)),
        PosColumn(text: 'P ${total.toStringAsFixed(2)}', width: 10, styles: const PosStyles(align: PosAlign.right)),
      ]));
    }
    
    commands.addAll(printer.horizontalLine());
    
    // Add totals
    final subtotal = saleData['subtotal'] ?? 0;
    final vat = saleData['vat'] ?? 0;
    final total = saleData['total'] ?? 0;
    
    commands.addAll(printer.row([
      PosColumn(text: 'SUBTOTAL:', width: 25, styles: const PosStyles(align: PosAlign.right)),
      PosColumn(text: 'P ${subtotal.toStringAsFixed(2)}', width: 15, styles: const PosStyles(align: PosAlign.right)),
    ]));
    commands.addAll(printer.row([
      PosColumn(text: 'VAT:', width: 25, styles: const PosStyles(align: PosAlign.right)),
      PosColumn(text: 'P ${vat.toStringAsFixed(2)}', width: 15, styles: const PosStyles(align: PosAlign.right)),
    ]));
    commands.addAll(printer.row([
      PosColumn(text: 'TOTAL:', width: 25, styles: const PosStyles(align: PosAlign.right, bold: true)),
      PosColumn(text: 'P ${total.toStringAsFixed(2)}', width: 15, styles: const PosStyles(align: PosAlign.right, bold: true)),
    ]));
    
    commands.addAll(printer.emptyLines(2));
    commands.addAll(printer.text('Thank you!', 
        styles: const PosStyles(align: PosAlign.center)));
    commands.addAll(printer.emptyLines(3));
    
    // Send to printer
    await PrintBluetoothThermal.writeBytes(commands);
  }
}
```

---

## 2.7 App Initialization

### 2.7.1 Load Settings on App Start

```dart
// lib/main.dart
import 'package:apex_pos/providers/sales_report_exclusion_provider.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Hive.initFlutter('cookie');
  await initDevice();
  
  // Load saved token from secure storage
  await loadTokenFromStorage();
  
  // Load Sales Report Exclusion settings
  await ref.read(salesReportExclusionSettingsProvider.notifier).fetchSettings();
  
  if (Platform.isIOS) {
    DartPingIOS.register();
  }
  
  runApp(
    const ProviderScope(
      child: MyApp(),
    ),
  );
}
```

---

## 2.8 Optional UI Enhancements

### 2.8.1 Exclusion Warning Indicator

Add a visual indicator when viewing filtered receipts to remind staff that they're seeing Sales Report-adjusted data.

```dart
// In receipt page or receipt list
class ReceiptWarning extends ConsumerWidget {
  const ReceiptWarning({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settings = ref.watch(salesReportExclusionSettingsProvider);
    
    if (!settings.isFilteringActive) {
      return const SizedBox.shrink();
    }
    
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: AppColor.warning.withOpacity(0.1),
        border: Border.all(color: AppColor.warning.withOpacity(0.3)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          Icon(Icons.warning_amber_rounded, color: AppColor.warning, size: 16),
          const SizedBox(width: 8),
          Text(
            'Sales Report Exclusion Active - Some items may be hidden',
            style: TextStyle(
              color: AppColor.warning,
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}
```

---

### 2.8.2 Cache Item Exclusion Status

For performance, you can cache which items are excluded to avoid repeated API calls.

```dart
// lib/providers/item_exclusion_cache_provider.dart
import 'package:flutter_riverpod/flutter_riverpod.dart';

final itemExclusionCacheProvider = StateNotifierProvider<
    ItemExclusionCacheNotifier,
    Map<int, bool>
>((ref) {
  return ItemExclusionCacheNotifier();
});

class ItemExclusionCacheNotifier extends StateNotifier<Map<int, bool>> {
  ItemExclusionCacheNotifier() : super({});

  /// Check if an item is excluded (uses cache first, then API)
  Future<bool> isExcluded(int itemId, int storeId) async {
    final cacheKey = _makeCacheKey(itemId, storeId);
    
    // Check cache first
    if (state.containsKey(cacheKey)) {
      return state[cacheKey]!;
    }
    
    // Call API
    final response = await ref.read(
      salesReportExclusionSettingsProvider.notifier
    ).isItemExcluded(itemId, storeId);
    
    // Update cache
    state = {...state, cacheKey: response};
    
    return response;
  }

  /// Clear cache (e.g., when settings change)
  void clearCache() {
    state = {};
  }

  /// Clear cache for a specific store
  void clearStoreCache(int storeId) {
    state = state.entries
        .where((entry) => !entry.key.toString().contains('store_$storeId'))
        .fold(<int, bool>{}, (map, entry) {
      map[entry.key] = entry.value;
      return map;
    });
  }

  String _makeCacheKey(int itemId, int storeId) {
    return 'item_$itemId_store_$storeId';
  }
}
```

---

## 2.9 File Changes Summary

| File | Action | Description |
|------|--------|-------------|
| `lib/services/api_services.dart` | Modify | Add Sales Report Exclusion API methods |
| `lib/models/sales_report_exclusion_settings.dart` | New | Model for settings |
| `lib/providers/sales_report_exclusion_provider.dart` | New | State management for settings |
| `lib/services/printing_service.dart` | Modify | Ensure printing uses original data |
| `lib/main.dart` | Modify | Load settings on app start |
| `lib/pages/receipts/receipt_page.dart` | Modify | Add warning indicator (optional) |
| `lib/providers/item_exclusion_cache_provider.dart` | New | Cache for item exclusion status (optional) |

**Total Files:** 4-6 files (depending on optional features)
**Estimated Effort:** 8-10 hours

---

## 2.10 Acceptance Criteria

### 2.10.1 Must Have

- [ ] POS sends ALL items to backend (no filtering)
- [ ] Receipt display shows data as returned by backend
- [ ] Printing ALWAYS uses original, unfiltered data
- [ ] API methods added for checking exclusion status
- [ ] Settings loaded on app start

### 2.10.2 Should Have

- [ ] Warning indicator when viewing filtered data
- [ ] Caching for exclusion status checks
- [ ] State management for settings

### 2.10.3 Nice to Have

- [ ] Periodic refresh of settings
- [ ] Local storage of settings for offline mode
- [ ] Visual distinction between filtered and original receipts

---

## 2.11 Testing Checklist

- [ ] Create sale with excluded items - verify all items sent to backend
- [ ] View receipt with exclusion enabled - verify filtered display
- [ ] View receipt with exclusion disabled - verify all items shown
- [ ] Print receipt with exclusion enabled - **verify ALL items printed**
- [ ] Print receipt with exclusion disabled - verify all items printed
- [ ] Toggle exclusion on/off - verify display updates correctly
- [ ] Toggle show original on/off - verify display updates correctly

---

## 2.12 Dependencies on Backend

The POS app depends on the backend implementing:

1. `/api/v1/sales-report-exclusion/settings` endpoint
2. `/api/v1/sales-report-exclusion/check/{item}` endpoint
3. `/sales/{id}` endpoint returning filtered data based on settings
4. `/sales/{id}/print` endpoint returning **original, unfiltered** data

All of these are specified in the [Backend Specification](10_BACKEND_SPEC.md).

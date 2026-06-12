# Tenant Branding — apex_pos Flutter Spec

**Status:** Spec to be executed by another agent against `/Users/richardleosala/Projects/RLCPS/apex_pos`.
**Date:** 2026-06-05
**Companion:** `plan.md` (backend authoritative — colors, palette keys, and logo URLs come from the apex_backend API; do not invent values here).
**Backend endpoint contract:** `GET /api/v1/branding` (see `plan.md` §7 for response shape).

---

## Rules for the executing agent

1. **The backend is authoritative.** This app consumes branding; it never defines it. Any visible color, brand name, or logo must trace to the API response.
2. **No new color literals.** Do not introduce `Color(0xFF...)` for brand colors. Use the runtime `BrandingState`.
3. **Theme toggle stays.** Light/dark mode persists; branding overrides the brand-related slots within whichever mode is active.
4. **Graceful offline.** First app start with no network must still show a usable UI — fall back to bundled defaults (`AppColor` initial values, `assets/logo/apex_pos_icon.svg`).
5. **No new packages** unless this spec lists them. Existing `flutter_riverpod`, `http`, `hive`, `hive_flutter`, `flutter_secure_storage`, `flutter_svg`, `cached_network_image` (if already present — verify) are sufficient.
6. **Match existing conventions:** feature-first folders, `*_service.dart`, `*_provider.dart`, `*_model.dart` naming.

---

## Codebase facts (from scan, 2026-06-05)

| Concern | Location | Notes |
|---|---|---|
| State management | `flutter_riverpod: ^2.4.9`, `ProviderScope` at `lib/main.dart:26-29` | Existing pattern |
| Theme files | `lib/config/light_theme.dart`, `lib/config/dark_theme.dart` | Static `ThemeData` literals |
| Theme mode toggle | `lib/config/theme_notifier.dart` | `ChangeNotifier`, persists to Hive `theme_mode` |
| Colors | `lib/config/env.dart` lines 10-53 | `class AppColor { static Color primary = const Color(0xFF6366F1); ... }` — mutable statics |
| MaterialApp wiring | `lib/main.dart` lines 50-62 | `ListenableBuilder(listenable: themeNotifier, ...)` |
| HTTP client | `lib/services/api_services.dart` | `_client` (http.Client), `Uri.http(_host, _apiSuffix + route)`, `setUserToken` adds Bearer header |
| Base host | `lib/config/globals.dart` lines 52-61 (`host` global) | Falls back to `192.168.1.201` |
| API suffix | `lib/services/api_services.dart` line 15 | `const String _apiSuffix = '/api/v1';` |
| Routes file | `lib/config/api_routes.dart` | Centralized constants; add a `branding` route here |
| Token persistence | `flutter_secure_storage` via `lib/services/secure_storage_service.dart` | Already wired |
| Hive box | `'device_settings'` opened at `lib/main.dart` line 20 (`initDevice()`) | Use for branding cache |
| Logo — drawer | `lib/pages/menu_list.dart` lines 70-78 | `SvgPicture.asset('assets/logo/apex_pos_icon.svg', width: 40, height: 40)` + hardcoded "Apex POS" text line 78 |
| Logo — login | `lib/pages/auth/login_page.dart` lines 176-210 | Gradient-box `"A"` text + hardcoded "Apex POS" line 210 |
| Assets | `assets/logo/apex_pos_icon.svg`, `apex_pos_logo.svg`, `apex_pos_logo_dark.svg` | Keep as fallbacks |
| API routes file | `lib/config/api_routes.dart` | No branding route yet |

---

## What to build (Flutter side)

### 1. Branding model

**New file:** `lib/models/branding_model.dart`

```dart
import 'dart:ui';

class BrandingState {
  final String paletteKey;
  final Color primary;
  final Color secondary;
  final Color accent;
  final Color onPrimary;
  final Color onSecondary;
  final String? logoUrl;        // may be null → falls back to asset
  final String brandName;       // never null; defaults to "APEX"
  final DateTime? updatedAt;    // sync key

  const BrandingState({
    required this.paletteKey,
    required this.primary,
    required this.secondary,
    required this.accent,
    required this.onPrimary,
    required this.onSecondary,
    required this.logoUrl,
    required this.brandName,
    required this.updatedAt,
  });

  factory BrandingState.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>;
    return BrandingState(
      paletteKey: data['palette_key'] as String,
      primary: _hex(data['primary_color'] as String),
      secondary: _hex(data['secondary_color'] as String),
      accent: _hex(data['accent_color'] as String),
      onPrimary: _hex(data['on_primary'] as String),
      onSecondary: _hex(data['on_secondary'] as String),
      logoUrl: data['logo_url'] as String?,
      brandName: (data['brand_name'] as String?) ?? 'APEX',
      updatedAt: data['updated_at'] != null
          ? DateTime.tryParse(data['updated_at'] as String)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
    'data': {
      'palette_key': paletteKey,
      'primary_color': '#${primary.value.toRadixString(16).padLeft(8, '0').substring(2)}',
      // ...same for the rest
      'logo_url': logoUrl,
      'brand_name': brandName,
      'updated_at': updatedAt?.toIso8601String(),
    },
  };

  static Color _hex(String hex) {
    final clean = hex.replaceFirst('#', '');
    return Color(int.parse('FF$clean', radix: 16));
  }

  /// The shipped Apex defaults — used when API call fails on first launch.
  factory BrandingState.fallback() => const BrandingState(
    paletteKey: 'apex_default',
    primary: Color(0xFF6366F1),
    secondary: Color(0xFF64748B),
    accent: Color(0xFFF6A623),
    onPrimary: Color(0xFFFFFFFF),
    onSecondary: Color(0xFFFFFFFF),
    logoUrl: null,
    brandName: 'APEX',
    updatedAt: null,
  );
}
```

### 2. Branding service

**New file:** `lib/services/branding_service.dart`

Responsibilities:
- Fetch `GET /api/v1/branding` using existing `_client` + `headers` pattern from `api_services.dart`.
- Read/write a Hive entry `branding_payload` (string-encoded JSON) on the existing `deviceBox`.
- Return `BrandingState` from cache instantly on cold start; refresh in background.

```dart
import 'dart:convert';
import 'package:apex_pos/config/api_routes.dart';
import 'package:apex_pos/config/globals.dart';
import 'package:apex_pos/models/branding_model.dart';
import 'package:apex_pos/services/api_services.dart';
import 'package:http/http.dart' as http;

class BrandingService {
  static const _hiveKey = 'branding_payload';

  /// Read cached branding from Hive. Returns null if absent.
  BrandingState? readCached() {
    final raw = deviceBox.get(_hiveKey) as String?;
    if (raw == null) return null;
    try {
      return BrandingState.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  /// Fetch from backend. Stores into Hive on success. Throws on HTTP error.
  Future<BrandingState> fetch() async {
    final uri = Uri.http(host, '/api/v1${ApiRoutes.branding}');
    final response = await http.get(uri, headers: ApiServices.instance.headers);
    if (response.statusCode != 200) {
      throw Exception('Branding fetch failed: ${response.statusCode}');
    }
    deviceBox.put(_hiveKey, response.body);
    return BrandingState.fromJson(
      jsonDecode(response.body) as Map<String, dynamic>,
    );
  }

  Future<void> clearCache() async {
    await deviceBox.delete(_hiveKey);
  }
}
```

**Note:** Pull the existing headers + auth pattern from `api_services.dart`. If `ApiServices.instance` isn't a thing today, refactor to expose the configured `_client` + `headers` map; do NOT duplicate token wiring.

### 3. Riverpod provider

**New file:** `lib/providers/branding_provider.dart`

A `StateNotifierProvider<BrandingNotifier, BrandingState>` that:
- Initializes from cache (sync — instant theme on cold start).
- Triggers a background fetch and updates state if `updated_at` changed.
- Exposes `refresh()` for the Settings page button + login completion hook.

```dart
import 'package:apex_pos/models/branding_model.dart';
import 'package:apex_pos/services/branding_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final brandingServiceProvider = Provider((_) => BrandingService());

final brandingProvider =
    StateNotifierProvider<BrandingNotifier, BrandingState>((ref) {
  final svc = ref.watch(brandingServiceProvider);
  return BrandingNotifier(svc);
});

class BrandingNotifier extends StateNotifier<BrandingState> {
  final BrandingService _svc;
  BrandingNotifier(this._svc) : super(_svc.readCached() ?? BrandingState.fallback()) {
    // Fire-and-forget refresh on construction; state updates if response differs.
    refresh();
  }

  Future<void> refresh() async {
    try {
      final fresh = await _svc.fetch();
      if (fresh.updatedAt != state.updatedAt) {
        state = fresh;
      }
    } catch (_) {
      // Keep current state; offline-friendly.
    }
  }

  Future<void> reset() async {
    await _svc.clearCache();
    state = BrandingState.fallback();
  }
}
```

### 4. Routes file

**Modify:** `lib/config/api_routes.dart`

Add:
```dart
static const String branding = '/branding';
```

### 5. Dynamic theme factories

**Modify:** `lib/config/light_theme.dart` and `lib/config/dark_theme.dart`

Replace the existing top-level `final ThemeData lightMode = ThemeData(...)` literal with a factory:

```dart
ThemeData buildLightTheme(BrandingState branding) {
  return ThemeData(
    brightness: Brightness.light,
    primaryColor: branding.primary,
    colorScheme: ColorScheme.light(
      primary: branding.primary,
      secondary: branding.secondary,
      onPrimary: branding.onPrimary,
      onSecondary: branding.onSecondary,
      tertiary: branding.accent,
    ),
    // ...keep all other existing ThemeData props as they are
  );
}
```

Same change for `buildDarkTheme(BrandingState branding)`.

**Do not delete** any non-brand ThemeData properties (typography, button styles, etc.). Only the brand-color slots get replaced.

### 6. AppColor reconciliation

**Modify:** `lib/config/env.dart`

The mutable `static Color primary` pattern works, but it's fragile (no listener, easy to drift). Pick the lowest-risk approach:

**Recommended:** keep `AppColor.*` for now but add a `BrandingMixer.apply(BrandingState)` helper that mutates the fields after each branding update. Trigger it from a `ref.listen(brandingProvider, (prev, next) => BrandingMixer.apply(next))` at the root of `MyApp`. This avoids touching every widget that references `AppColor.primary`.

```dart
class BrandingMixer {
  static void apply(BrandingState b) {
    AppColor.primary = b.primary;
    AppColor.violet = b.secondary;        // existing slot used in login gradient
    AppColor.success = AppColor.success;  // unchanged
    // ...map each AppColor slot deliberately. Document why each maps where it does.
  }
}
```

**Alternative (cleaner, larger blast radius):** delete the mutable `AppColor` statics and migrate every reference to read from `ref.watch(brandingProvider)`. Roughly 100+ touch sites — defer unless the agent has appetite.

### 7. MaterialApp consumer

**Modify:** `lib/main.dart` lines 50-62

Convert `MyApp` to a `ConsumerStatefulWidget` (or `ConsumerWidget` if state is no longer needed). Watch `brandingProvider`; pass to theme factories; install the listener that runs `BrandingMixer.apply()`.

```dart
class MyApp extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final branding = ref.watch(brandingProvider);

    // Side-effect: keep legacy AppColor statics in sync.
    ref.listen<BrandingState>(brandingProvider, (_, next) {
      BrandingMixer.apply(next);
    });
    BrandingMixer.apply(branding); // initial

    return ListenableBuilder(
      listenable: themeNotifier,
      builder: (context, child) {
        return MaterialApp(
          theme: buildLightTheme(branding),
          darkTheme: buildDarkTheme(branding),
          themeMode: themeNotifier.themeMode,
          routes: routes,
          home: const LoginPage(),
        );
      },
    );
  }
}
```

### 8. Refetch hooks

Add a `ref.read(brandingProvider.notifier).refresh()` call:

1. **After successful login** — `lib/controllers/auth_controller.dart` (or wherever login success lives). Token is now set, so the request will return the right tenant's branding.
2. **On the Settings page** — `lib/pages/settings/settings_page.dart`: add a "Refresh branding" button that calls `refresh()`. Show a SnackBar on success/failure.

### 9. Logo widget

**New file:** `lib/components/branding/branding_logo.dart`

```dart
import 'package:apex_pos/providers/branding_provider.dart';
import 'package:cached_network_image/cached_network_image.dart'; // if already in pubspec; otherwise use Image.network
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';

class BrandingLogo extends ConsumerWidget {
  final double size;
  const BrandingLogo({super.key, this.size = 40});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final branding = ref.watch(brandingProvider);
    final url = branding.logoUrl;
    if (url == null || url.isEmpty) {
      return SvgPicture.asset(
        'assets/logo/apex_pos_icon.svg',
        width: size,
        height: size,
      );
    }
    return Image.network(
      url,
      width: size,
      height: size,
      errorBuilder: (_, __, ___) => SvgPicture.asset(
        'assets/logo/apex_pos_icon.svg',
        width: size,
        height: size,
      ),
    );
  }
}
```

If `cached_network_image` is not in `pubspec.yaml`, use `Image.network` — do not add the package without approval.

### 10. Replace hardcoded logo references

**Modify:** `lib/pages/menu_list.dart` (lines 70-75 and 78)

Replace the `SvgPicture.asset(...)` call with `BrandingLogo(size: 40)`. Replace the hardcoded `"Apex POS"` text with `Text(ref.watch(brandingProvider).brandName)` — convert the widget to a `ConsumerWidget` if it isn't already.

**Modify:** `lib/pages/auth/login_page.dart` (lines 176-210)

Two changes:
- Inside the gradient `Container`, swap the `"A"` `Text` for `BrandingLogo(size: 56)` (sized to fit the 80×80 box).
- Replace the hardcoded `"Apex POS"` brand-name `Text` on line 210 with `ref.watch(brandingProvider).brandName`.

**Modify:** any other `apex_pos_logo.svg` / `apex_pos_logo_dark.svg` / `apex_pos_icon.svg` references found during build. Search the repo before merging.

### 11. Hive key conventions

Add to the docs (or wherever Hive keys are catalogued) — `branding_payload` is the new well-known key on the `device_settings` box. Stored as raw JSON string.

---

## Files (apex_pos)

### New
- `lib/models/branding_model.dart`
- `lib/services/branding_service.dart`
- `lib/providers/branding_provider.dart`
- `lib/components/branding/branding_logo.dart`
- `test/branding_state_test.dart` — JSON parse, fallback, color hex roundtrip
- `test/branding_service_test.dart` — cache read/write (use a fake Hive box)
- `test/branding_provider_test.dart` — initialState falls back, refresh updates state on `updated_at` change

### Modified
- `lib/config/api_routes.dart` — add `branding` route
- `lib/config/light_theme.dart` — convert to `buildLightTheme(BrandingState)`
- `lib/config/dark_theme.dart` — convert to `buildDarkTheme(BrandingState)`
- `lib/config/env.dart` — add `BrandingMixer.apply()` helper alongside `AppColor`
- `lib/main.dart` — convert `MyApp` to `ConsumerWidget`, watch + listen `brandingProvider`, pass branding into theme factories
- `lib/pages/menu_list.dart` — swap drawer logo + brand name
- `lib/pages/auth/login_page.dart` — swap login logo + brand name
- `lib/controllers/auth_controller.dart` (or login completion handler) — call `refresh()` after successful auth
- `lib/pages/settings/settings_page.dart` — "Refresh branding" button

---

## Acceptance criteria

1. **Cold start, online, token present, tenant has branding:** app shows tenant logo + colors immediately (from cache after first run, from fetch on first run).
2. **Cold start, offline:** falls back to last cached branding; if no cache, ships defaults. No crashes.
3. **Login as Tenant A, log out, login as Tenant B:** UI updates to Tenant B's branding within ~1s of login success.
4. **Invalid hex from backend (e.g., null fields):** `BrandingState.fromJson` defensively falls back per-field; never crashes.
5. **Logo URL returns 404:** logo falls back to bundled SVG silently.
6. **All hardcoded color literals for brand slots in `env.dart` are reachable via `BrandingMixer.apply()`** — verified by reading the mixer code.
7. **No widget reads brand colors via `const Color(0xFF...)` literals** — checked by `grep "Color(0xFF" lib/pages lib/components` returning only non-brand uses (status, danger, success, etc.).
8. **Theme toggle (light/dark) still works** independent of branding.
9. **`flutter test` passes** for the three new test files.
10. **`flutter analyze` returns zero warnings** for the new and modified files.

---

## Phasing

1. Model + tests.
2. Service + cache + tests.
3. Provider + tests.
4. Theme factories + MaterialApp wiring + BrandingMixer.
5. Logo widget + replace drawer + login references.
6. Refetch hooks (post-login, Settings button).
7. `flutter analyze` + `flutter test` + smoke test against staging backend.

Single PR in apex_pos. Merge after the backend PR (this app) lands in `main` and the API endpoint is reachable.

---

## Open questions for the agent

- Confirm `cached_network_image` is in `pubspec.yaml` before using; otherwise fall back to `Image.network`. Do not add new deps without asking.
- Confirm the exact name and shape of the existing `ApiServices` singleton — adjust import paths accordingly. Do not duplicate token/auth wiring.
- Where exactly is "login success" handled — `auth_controller.dart` or inside `login_page.dart`? Use the cleaner of the two for the `refresh()` hook.
- If there are additional logo references found via `grep` (printed receipts, splash screens), confirm with the user before swapping — receipts are explicitly out of scope per the backend plan.
- If `BrandingMixer` mapping ambiguity arises (e.g., which AppColor slot is "accent"), default to mapping by semantic intent and document the choice inline.

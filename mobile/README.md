# Tax Simulator — Mobile (Flutter)

Cross-platform mobile client (Android + iOS) for the Thai Personal Income Tax
Simulator. It is a pure client over the Laravel REST API (`/api/v1`). The
backend is the source of truth — **the app never re-implements tax logic**, it
calls `/tax/calculate`.

Full plan: [`../docs/mobile/MOBILE_APP_PLAN.md`](../docs/mobile/MOBILE_APP_PLAN.md)

## Prerequisites

- Flutter SDK (stable, 3.22+) — https://docs.flutter.dev/get-started/install
- Android Studio (Android SDK/emulator) and/or Xcode (iOS, macOS only)

## First-time setup

This folder holds the app source (`lib/`, `pubspec.yaml`, tests) but not the
generated native shells. Generate them once, in place:

```bash
cd mobile
flutter create . --platforms=android,ios --org com.taxsimulator
flutter pub get
```

`flutter create .` fills in `android/` and `ios/` without touching `lib/` or
`pubspec.yaml`.

## Run

Start the backend first (from the repo root):

```bash
docker compose up -d --wait
```

Then run the app. The API base URL defaults per platform:

- Android emulator → `http://10.0.2.2:8088`
- iOS simulator → `http://localhost:8088`

```bash
flutter run
```

Point at another backend without editing code:

```bash
flutter run --dart-define=API_BASE_URL=https://staging.example.com
```

## Project layout

```
lib/
  core/
    config/env.dart            API base URL per platform / --dart-define
    device_name.dart           device label for the required device_name field
    network/api_client.dart    dio + bearer-token interceptor + 401 handling
    network/api_exception.dart typed error from the Laravel error envelope
    storage/token_storage.dart secure token (Keychain / Keystore)
    theme/app_theme.dart       design tokens mirrored from the web app
  features/
    auth/  (data / state / ui) register, login, logout, session restore
    tax/   (data / ui)         guest calculator over /tax/calculate
    home/                      signed-in home menu
  routing/app_router.dart      go_router with auth redirects
```

## Build for release

```bash
flutter build appbundle   # Android (Play)
flutter build ipa         # iOS (App Store) — requires macOS + Xcode
```

## Test

```bash
flutter analyze
flutter test
```

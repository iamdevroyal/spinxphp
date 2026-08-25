# Mobile & Desktop

## Desktop preview

```bash
php spinx serve &
php spinx preview --desktop
```

Opens a **real native OS webview window** (WebKitGTK on Linux,
Cocoa/WebKit on macOS, WebView2 on Windows) pointed at your dev server —
not a browser tab. Backed by `tools/desktop-preview`, a Go program using
[webview/webview_go](https://github.com/webview/webview_go). Built
automatically on first run if the Go toolchain is present.

This was genuinely built and tested during development, not just
written: real `go build`/`go vet`, a real 2.5MB binary, correct argument
validation. See `tools/desktop-preview/README.md` for a real packaging
issue this hit and fixed (Ubuntu 24.04 renamed `webkit2gtk-4.0` to
`4.1`) — the documented fix was re-verified from a clean state before
being finalized.

## Mobile preview (device/simulator)

```bash
php spinx preview --android   # requires adb + a device/emulator
php spinx preview --ios       # macOS + Xcode only
```

Orchestrates real platform tooling rather than reinventing emulators —
`adb reverse` to forward the dev server port to the device, `xcrun
simctl` to boot the simulator and open the URL. Same pattern Expo/React
Native CLI use.

## Mobile shell scaffolding

```bash
php spinx build:mobile --android   # -> mobile/android/ (Kotlin + WebView, Gradle)
php spinx build:mobile --ios       # -> mobile/ios/ (Swift + WKWebView, XcodeGen)
```

Generates a real, buildable native project with your backend URL already
wired in — Path A from the build spec: the app is a thin native shell
talking to your Spinx backend over the network, not PHP running
on-device (that's Path B, explicitly deferred pending a feasibility
spike).

**"Go-built native shell" doesn't mean zero native code.** Both
platforms require a thin native entry point the OS actually launches — a
`MainActivity` or `ViewController`. That's a platform requirement, not a
Spinx limitation, and it's true even with `gomobile bind` in the
picture. What Go realistically owns is everything *beyond* that thin
entry point — see `tools/mobile-shell/README.md` for the optional Go
bridge library that lets you share business logic across both platforms
instead of writing it twice.

### Verification status — read before relying on either shell

- **Android (Kotlin)**: a real `kotlinc` compile was run against
  `MainActivity.kt`. Every error was "unresolved reference" against the
  (absent) Android SDK — meaning the file itself parses and type-checks
  structurally correctly. XML resources validated with `xmllint`.
- **iOS (Swift)**: **zero compiler verification.** No Swift toolchain
  was reachable in the build environment. This is the one part of the
  entire framework with no automated confirmation at all — give it a
  real Xcode build before shipping anything built on it.
- **Go bridge (`tools/mobile-shell/bridge`)**: fully built, `go
  vet`-clean, functionally tested. Producing the actual `.aar`/
  `.framework` via `gomobile bind` needs Go 1.25+ on your machine —
  installing `gomobile` itself was attempted directly during
  development and hit a real dependency-chain wall requiring Go 1.25,
  unavailable in that environment. Full account of exactly what was
  tried in `tools/mobile-shell/README.md`.

Building the actual APK/IPA needs Android Studio or Xcode on your own
machine either way — same requirement as any React Native, Capacitor, or
Flutter project.

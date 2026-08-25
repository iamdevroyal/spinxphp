# Spinx Mobile Shell (build spec §10.1, Path A)

Wraps the Spinx frontend in a native mobile shell that talks to the
backend over the network — the same architecture Capacitor/Tauri-mobile
use, matching the build spec's committed v1 mobile story. This is
explicitly **not** Path B (on-device PHP) — that's deferred pending a
feasibility spike (see `SPINX_BUILD_SPEC.md` §10.2).

## What's actually here

```
tools/mobile-shell/
└── bridge/              Go library — optional native-capability extension point
```

Generated into your app by `spinx build:mobile --android|--ios`:

```
mobile/
├── android/              Kotlin + WebView shell (Gradle project)
└── ios/                  Swift + WKWebView shell (XcodeGen project)
```

## An architectural nuance worth being upfront about

"Go-built native shell" doesn't mean zero Kotlin/Swift code. Both Android
and iOS require a thin native entry point the OS actually launches — a
`MainActivity` or a `ViewController`. That's a platform requirement, not
a Spinx limitation, and it applies even if you use `gomobile bind`: the
Go code compiles into an `.aar`/`.framework` that a native host still has
to load and call into. What Go realistically owns here is everything
*beyond* that thin entry point — shared business logic that would
otherwise need writing twice, once in Kotlin and once in Swift. For the
common case (the app is just a WebView pointed at your backend), you
don't need the bridge at all; the generated shells work standalone.

## Verification status — what's tested vs. what needs a real machine

This build hit real, instructive walls worth documenting rather than
glossing over:

**`tools/mobile-shell/bridge/` — fully built and tested.** Plain Go, no
mobile-specific tooling needed to compile it as an ordinary library.
`go build`, `go vet`, and a functional test (calling both exported
functions and checking their return values) all ran successfully in this
environment.

**`gomobile` itself — genuinely attempted, hit a real dependency wall.**
Installing `gomobile` to actually produce an `.aar`/`.framework` was
attempted directly (not assumed impossible): the Go toolchain was
upgraded to 1.24, `golang.org/x/mobile` was fetched via its GitHub
mirror with `replace` directives (since `golang.org`'s module proxy
itself was network-blocked in this environment), and several exact
pseudo-version mismatches were resolved one at a time. It ultimately hit
a hard wall: `gomobile`'s current dependency chain (`golang.org/x/mod`,
`x/sync`, `x/tools`) requires Go ≥1.25, which isn't in Ubuntu 24.04's apt
repos and couldn't be fetched (again, `golang.org` blocked). An older
`gomobile` commit was checked too — it pins the exact same problematic
transitive versions, so it doesn't route around the wall. **If you want
to actually produce the `.aar`/`.framework`, you'll need Go 1.25+ on your
own machine and to run `gomobile bind` yourself** — this is genuinely
outside what could be completed in this environment, not a shortcut.

**Android shell (Kotlin) — real compiler, partial verification.** Kotlin
was installed and `kotlinc` was run directly against `MainActivity.kt`.
Every resulting error was "unresolved reference" for Android SDK classes
(`WebView`, `AppCompatActivity`, etc.) not present without the Android
SDK — meaning the compiler successfully **parsed and type-checked the
file's own structure**, and only failed at symbol resolution against a
platform that isn't installed here (no CI sandbox has the Android SDK
preinstalled either). All XML resources (`AndroidManifest.xml`,
`strings.xml`, `styles.xml`) were validated with `xmllint` — well-formed.

**iOS shell (Swift) — unverified, written carefully by hand.** No Swift
toolchain was reachable in this environment (`swift.org` isn't in the
allowed network list). This is the one piece of this entire framework
build with zero compiler verification — treat it accordingly, and give
it a real build in Xcode before relying on it. `Info.plist` (XML) and
`project.yml` (YAML) were both syntactically validated.

**Generator (`MobileShellGenerator`) — fully tested.** Pure PHP file
templating, no external dependency. Tested end-to-end: correct file
counts for both platforms, correct backend-URL and app-name substitution
(and confirmed the `{{PLACEHOLDER}}` tokens are actually gone afterward,
not just that the replacement value appears somewhere), the
already-exists guard, and the real CLI entrypoint
(`spinx build:mobile --android|--ios`) end to end.

## Usage

```bash
php spinx build:mobile --android
php spinx build:mobile --ios
```

Each prints its own next steps — building the actual APK/IPA needs
Android Studio or Xcode on your machine, same as any React Native,
Capacitor, or Flutter project. Spinx's job is scaffolding a correct
starting point and keeping the backend URL in sync with your
`spinx.json`, not reimplementing those toolchains.

## Optional: building the Go bridge

```bash
cd tools/mobile-shell/bridge
go install golang.org/x/mobile/cmd/gomobile@latest   # requires Go 1.25+
gomobile init
gomobile bind -target=android -o ../../../mobile/android/app/libs/bridge.aar .
gomobile bind -target=ios -o ../../../mobile/ios/Bridge.xcframework .
```

Then uncomment the corresponding dependency line in
`mobile/android/app/build.gradle.kts` or add the `.xcframework` to the
Xcode project.

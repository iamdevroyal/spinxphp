// Package bridge is the native-capability extension point for Spinx
// mobile shells (build spec §10.1, Path A).
//
// IMPORTANT ARCHITECTURAL NOTE, stated plainly rather than glossed over:
// a mobile app is not "just Go" even with gomobile in the picture. Both
// Android and iOS require a thin native entry point — a Kotlin Activity
// or a Swift ViewController — that the OS actually launches; there is no
// way around that platform requirement, gomobile included. What gomobile
// *does* let you do is push everything beyond that thin entry point into
// Go: this package compiles via `gomobile bind` into an .aar (Android)
// or .framework (iOS) that the native host calls into, so business logic
// beyond "show a WebView pointed at the backend" can be written once in
// Go and shared across both platforms, rather than duplicated in Kotlin
// and Swift.
//
// For the common case — the mobile app is just a WebView pointed at the
// Spinx backend over the network — you don't need this package at all;
// the native shell scaffolds (tools/mobile-shell/android,
// tools/mobile-shell/ios) work standalone. Reach for this when a feature
// needs real native capability the WebView/JS layer can't reach (secure
// keychain/keystore access, biometric auth, background sync, etc.) and
// you want that logic written once instead of twice.
//
// gomobile bind's type restrictions apply to every exported function and
// type here: only string, bool, numeric types (not 64-bit int on
// certain older Android ABIs), []byte, and other bindable types/structs
// are supported — no generics, no channels, no complex struct graphs.
// See https://pkg.go.dev/golang.org/x/mobile/cmd/gomobile for the full
// list before adding to this package.
package bridge

import "fmt"

// Ping is a minimal smoke-test binding proving the bridge is wired into
// the native shell correctly. Call from Kotlin as `Bridge.ping()` after
// `gomobile bind -target=android`, or from Swift as `BridgePing()` after
// `-target=ios`.
func Ping() string {
	return "pong from Spinx Go bridge"
}

// BackendHealthCheckURL builds the health-check endpoint URL for a given
// backend base URL — a tiny example of logic worth sharing between
// platforms rather than reimplementing in both Kotlin and Swift.
func BackendHealthCheckURL(backendBaseURL string) string {
	return fmt.Sprintf("%s/health", backendBaseURL)
}

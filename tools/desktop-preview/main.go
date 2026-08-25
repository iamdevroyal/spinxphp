// Command spinx-desktop-preview opens a native OS webview window pointed
// at a URL — the backend for `spinx preview --desktop` (build spec §9).
//
// This is deliberately NOT "open the URL in the default browser": the
// spec calls for "a native webview window... for quick desktop testing
// without a browser", and this same Go+webview foundation is the
// documented starting point for the mobile shell compiler in build step
// 9 (Path A: wrap the compiled frontend in a Go-built native shell) — so
// building it as a real native window now, rather than a browser-tab
// shortcut, means step 9 extends this rather than replacing it.
//
// Usage: spinx-desktop-preview <url>
package main

import (
	"fmt"
	"os"

	webview "github.com/webview/webview_go"
)

func main() {
	if len(os.Args) < 2 {
		fmt.Fprintln(os.Stderr, "usage: spinx-desktop-preview <url>")
		os.Exit(1)
	}

	url := os.Args[1]

	w := webview.New(false)
	defer w.Destroy()

	w.SetTitle("Spinx — Desktop Preview")
	w.SetSize(1100, 750, webview.HintNone)
	w.Navigate(url)
	w.Run()
}

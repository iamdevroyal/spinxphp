# Spinx Desktop Preview Shell

Native OS webview window for `spinx preview --desktop` (build spec §9).
Uses [webview/webview_go](https://github.com/webview/webview_go), a real
CGo binding over the platform's native web engine (WebKitGTK on Linux,
Cocoa/WebKit on macOS, WebView2 on Windows) — this opens an actual native
window, not a browser tab.

This is also the foundation build step 9 (Go-based mobile shell compiler,
Path A) extends, per the build spec — wrapping the compiled frontend in a
Go-built native shell that talks to the Spinx backend over the network.

## Building

`spinx preview --desktop` builds this automatically on first run if you
have the Go toolchain installed (`go build -o spinx-desktop-preview .`,
cached after that). Manual build:

```bash
go mod tidy
go build -o spinx-desktop-preview .
./spinx-desktop-preview http://localhost:8080
```

## Platform build dependencies

- **Linux**: `gtk+-3.0` and `webkit2gtk` dev packages, plus `pkg-config`.
- **macOS**: Xcode command line tools (Cocoa/WebKit are system frameworks).
- **Windows**: the WebView2 runtime (pre-installed on Windows 10/11) —
  add `-ldflags="-H windowsgui"` to the build command to suppress the
  console window.

### Known packaging quirk (Ubuntu 24.04 / Debian trixie+)

`webview_go`'s CGo directives hardcode `pkg-config webkit2gtk-4.0`, but
current Ubuntu/Debian only ship `webkit2gtk-4.1` — the package was
renamed upstream. This was hit and confirmed while building this exact
shell. Fix with a pkg-config compatibility shim:

```bash
sudo apt install libgtk-3-dev libwebkit2gtk-4.1-dev pkg-config

# Alias 4.0 -> 4.1 so webview_go's hardcoded pkg-config call resolves.
# Two steps, not one: the first pass also (incorrectly) renames the
# Libs line's -lwebkit2gtk-4.1 to -lwebkit2gtk-4.0, which doesn't exist
# as a real library file — the second sed corrects that back.
PCDIR=$(pkg-config --variable pc_path pkg-config | cut -d: -f1)
sudo mkdir -p "$PCDIR"
sed \
  -e 's/webkit2gtk-4.1/webkit2gtk-4.0/' \
  -e 's/Requires: .*/Requires: javascriptcoregtk-4.1, libsoup-3.0/' \
  /usr/lib/x86_64-linux-gnu/pkgconfig/webkit2gtk-4.1.pc \
  | sudo tee "$PCDIR/webkit2gtk-4.0.pc" > /dev/null
sudo sed -i 's/-lwebkit2gtk-4.0/-lwebkit2gtk-4.1/' "$PCDIR/webkit2gtk-4.0.pc"
```

(Older Ubuntu 22.04 and earlier ship `webkit2gtk-4.0` directly — no shim
needed there.)

## Verification status

Built and `go vet`-clean in the environment this framework was developed
in (Ubuntu 24.04, after applying the shim above) — confirmed a real
2.5MB dynamically-linked ELF binary that correctly validates its
command-line argument. Launching the actual GUI window wasn't verifiable
in that environment (headless, no display server) — the window-open path
itself should get a real smoke test on a machine with a display before
you rely on it.

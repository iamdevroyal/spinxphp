import UIKit
import WebKit

/// The thin native host every Spinx mobile shell needs (build spec
/// §10.1, Path A) — a WKWebView pointed at the compiled frontend served
/// by the Spinx backend over the network, matching Capacitor/
/// Tauri-mobile's own architecture. This is deliberately NOT running PHP
/// on-device (that's Path B, explicitly deferred pending a feasibility
/// spike — see SPINX_BUILD_SPEC.md §10.2).
///
/// backendURL below is templated in by `spinx build:mobile --ios` from
/// your project's spinx.json — see Spinx\Generator\MobileShellGenerator.
class ViewController: UIViewController, WKNavigationDelegate {

    private let backendURL = "{{BACKEND_URL}}"
    private var webView: WKWebView!

    override func loadView() {
        let configuration = WKWebViewConfiguration()
        webView = WKWebView(frame: .zero, configuration: configuration)
        webView.navigationDelegate = self
        view = webView
    }

    override func viewDidLoad() {
        super.viewDidLoad()

        // Optional native bridge — see tools/mobile-shell/bridge/README.md.
        // Uncomment after gomobile-binding the bridge library and adding
        // the resulting Bridge.xcframework to the Xcode project (see
        // project.yml):
        // let healthCheckURL = BridgeBackendHealthCheckURL(backendURL)

        guard let url = URL(string: backendURL) else { return }
        webView.load(URLRequest(url: url))
    }
}

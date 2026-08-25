package africa.spinx.shell

import android.os.Bundle
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.appcompat.app.AppCompatActivity

/**
 * The thin native host every Spinx mobile shell needs (build spec §10.1,
 * Path A) — a WebView pointed at the compiled frontend served by the
 * Spinx backend over the network, matching Capacitor/Tauri-mobile's own
 * architecture. This is deliberately NOT running PHP on-device (that's
 * Path B, explicitly deferred pending a feasibility spike — see
 * SPINX_BUILD_SPEC.md §10.2).
 *
 * BACKEND_URL below is templated in by `spinx build:mobile --android`
 * from your project's spinx.json — see
 * Spinx\Generator\MobileShellGenerator.
 */
class MainActivity : AppCompatActivity() {

    private val backendUrl = "{{BACKEND_URL}}"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val webView = WebView(this)
        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.webViewClient = WebViewClient() // keeps navigation inside the WebView rather than opening the system browser

        // Optional native bridge — see tools/mobile-shell/bridge/README.md.
        // Uncomment after gomobile-binding the bridge library and adding
        // the resulting .aar to app/libs/ (see app/build.gradle.kts):
        // val healthCheckUrl = Bridge.backendHealthCheckURL(backendUrl)

        setContentView(webView)
        webView.loadUrl(backendUrl)
    }
}

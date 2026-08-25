plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "africa.spinx.shell"
    compileSdk = 34

    defaultConfig {
        applicationId = "africa.spinx.shell"
        minSdk = 24
        targetSdk = 34
        versionCode = 1
        versionName = "1.0"
    }

    buildTypes {
        release {
            isMinifyEnabled = false
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.webkit:webkit:1.11.0")

    // Uncomment after running `gomobile bind -target=android -o app/libs/bridge.aar`
    // from tools/mobile-shell/bridge/ (see that directory's own README —
    // this is an optional extension point, not required for a basic
    // WebView-pointed-at-the-backend shell):
    // implementation(files("libs/bridge.aar"))
}

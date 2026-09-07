import java.io.FileInputStream
import java.util.Properties

plugins {
    id("com.android.application")
    id("kotlin-android")
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.zoomnearby.zoompos"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        applicationId = "com.zoomnearby.zoompos"
        minSdk = 23
        targetSdk = flutter.targetSdkVersion
        multiDexEnabled = true
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        create("release") {
            val keystoreProperties = Properties()
            val keystorePropertiesFile = rootProject.file("key.properties")
            if (keystorePropertiesFile.exists()) {
                keystoreProperties.load(FileInputStream(keystorePropertiesFile))
                val storePath = keystoreProperties.getProperty("storeFile")
                if (storePath != null) {
                    val candidate = file(storePath)
                    storeFile = when {
                        candidate.exists() -> candidate
                        rootProject.file(storePath).exists() -> rootProject.file(storePath)
                        rootProject.file("../$storePath").exists() -> rootProject.file("../$storePath")
                        else -> candidate
                    }
                }
                storePassword = keystoreProperties.getProperty("storePassword")
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
            }

            // Fallback to environment variables if not specified in key.properties
            val envStorePath = System.getenv("KEYSTORE_FILE") ?: System.getenv("KEYSTORE_PATH")
            if (storeFile == null && envStorePath != null) {
                val candidate = file(envStorePath)
                storeFile = when {
                    candidate.exists() -> candidate
                    rootProject.file(envStorePath).exists() -> rootProject.file(envStorePath)
                    rootProject.file("../$envStorePath").exists() -> rootProject.file("../$envStorePath")
                    else -> candidate
                }
            }
            if (storePassword == null) {
                storePassword = System.getenv("KEYSTORE_PASSWORD")
            }
            if (keyAlias == null) {
                keyAlias = System.getenv("KEY_ALIAS")
            }
            if (keyPassword == null) {
                keyPassword = System.getenv("KEY_PASSWORD")
            }

            // Fallback to repository root keystore zoom-pos.jks if present
            if (storeFile == null || !storeFile!!.exists()) {
                val repoKeystore = rootProject.file("../zoom-pos.jks")
                if (repoKeystore.exists()) {
                    storeFile = repoKeystore
                    if (storePassword == null) storePassword = "123456789"
                    if (keyAlias == null) keyAlias = "zoom-pos"
                    if (keyPassword == null) keyPassword = "123456789"
                }
            }
        }
    }

    buildTypes {
        release {
            val releaseConfig = signingConfigs.getByName("release")
            signingConfig = if (releaseConfig.storeFile?.exists() == true && !releaseConfig.keyAlias.isNullOrEmpty()) {
                releaseConfig
            } else {
                signingConfigs.getByName("debug")
            }
        }
    }
}

flutter {
    source = "../.."
}

dependencies {
    // PushApplication initializes Firebase before the Flutter background
    // isolate, so the app module needs FirebaseApp on its own compile path.
    implementation(platform("com.google.firebase:firebase-bom:34.18.0"))
    implementation("com.google.firebase:firebase-messaging")
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}

package com.zoomnearby.zoompos

import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import io.flutter.app.FlutterApplication
import org.json.JSONObject

/**
 * Restores the SuperAdmin-provided public Firebase Android options before
 * FirebaseMessagingService is created in a cold/terminated app process.
 * The first launch obtains these values from the backend and stores them via
 * shared_preferences; subsequent lock-screen pushes can therefore wake the
 * app without a bundled google-services.json.
 */
class PushApplication : FlutterApplication() {
    override fun onCreate() {
        super.onCreate()
        if (FirebaseApp.getApps(this).isNotEmpty()) return

        try {
            val prefs = getSharedPreferences("FlutterSharedPreferences", MODE_PRIVATE)
            val raw = prefs.getString("flutter.zoom_pos.push_config", null) ?: return
            val config = JSONObject(raw)
            if (!config.optBoolean("enabled", false)) return

            val apiKey = config.optString("android_api_key")
            val appId = config.optString("android_app_id")
            val senderId = config.optString("messaging_sender_id")
            val projectId = config.optString("project_id")
            if (apiKey.isBlank() || appId.isBlank() || senderId.isBlank() || projectId.isBlank()) return

            val options = FirebaseOptions.Builder()
                .setApiKey(apiKey)
                .setApplicationId(appId)
                .setGcmSenderId(senderId)
                .setProjectId(projectId)
                .build()
            FirebaseApp.initializeApp(this, options)
        } catch (_: Exception) {
            // Flutter will retry dynamic initialization on the next app launch.
        }
    }
}

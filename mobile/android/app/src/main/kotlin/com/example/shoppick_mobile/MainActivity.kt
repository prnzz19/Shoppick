package com.example.shoppick_mobile

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import android.content.pm.PackageManager

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "shoppick/maps").setMethodCallHandler { call, result ->
            if (call.method == "configured") {
                val info = packageManager.getApplicationInfo(packageName, PackageManager.GET_META_DATA)
                result.success(!info.metaData?.getString("com.google.android.geo.API_KEY").isNullOrBlank())
            } else result.notImplemented()
        }
    }
}

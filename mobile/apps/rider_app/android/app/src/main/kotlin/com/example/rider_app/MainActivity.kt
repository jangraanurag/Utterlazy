package com.example.rider_app

import android.content.Intent
import android.os.Bundle
import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        startWatchdogService()
    }

    private fun startWatchdogService() {
        val intent = Intent(this, WatchdogService::class.java)
        startForegroundService(intent)
    }
}

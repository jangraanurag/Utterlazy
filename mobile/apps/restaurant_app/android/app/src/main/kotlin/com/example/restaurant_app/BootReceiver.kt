package com.example.restaurant_app

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_BOOT_COMPLETED ||
            intent.action == "android.intent.action.QUICKBOOT_POWERON") {
            // Start watchdog service directly
            val watchdogIntent = Intent(context, WatchdogService::class.java)
            context.startForegroundService(watchdogIntent)

            // Also launch the app to start Flutter background service
            val launchIntent = context.packageManager.getLaunchIntentForPackage(context.packageName)
            if (launchIntent != null) {
                launchIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                launchIntent.putExtra("auto_start_service", true)
                context.startActivity(launchIntent)
            }
        }
    }
}

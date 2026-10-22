package com.example.rider_app

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

class WatchdogAlarmReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        when (intent.action) {
            WatchdogService.ACTION_RESTART -> {
                val serviceIntent = Intent(context, WatchdogService::class.java)
                context.startForegroundService(serviceIntent)
            }
        }
    }
}

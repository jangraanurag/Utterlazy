package com.example.rider_app

import android.app.ActivityManager
import android.app.AlarmManager
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import android.os.SystemClock

class WatchdogService : Service() {
    companion object {
        private const val CHANNEL_ID = "utterlazy_watchdog_channel"
        private const val NOTIFICATION_ID = 999
        private const val CHECK_INTERVAL_MS = 120000L
        private const val RESTART_DELAY_MS = 5000L
        private const val ALARM_REQUEST_CODE = 7777
        const val ACTION_RESTART = "com.example.rider_app.RESTART_BACKGROUND_SERVICE"
    }

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
        try {
            startForeground(NOTIFICATION_ID, createNotification())
        } catch (_: Exception) {}
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_RESTART -> {
                if (isMainProcessDead() && wasRiderOnline()) {
                    restartMainProcess()
                }
            }
        }
        scheduleAlarm()
        return START_STICKY
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun createNotificationChannel() {
        val channel = NotificationChannel(
            CHANNEL_ID,
            "UtterLazy Watchdog",
            NotificationManager.IMPORTANCE_MIN
        ).apply { setShowBadge(false) }
        (getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager).createNotificationChannel(channel)
    }

    private fun createNotification(): Notification {
        val builder = Notification.Builder(this, CHANNEL_ID)
            .setContentTitle("UtterLazy Delivery")
            .setContentText("Service is running")
            .setSmallIcon(com.khaalo.rider_app.R.mipmap.ic_launcher)
            .setOngoing(true)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            builder.setForegroundServiceBehavior(Notification.FOREGROUND_SERVICE_IMMEDIATE)
        }
        return builder.build()
    }

    private fun scheduleAlarm() {
        val alarmManager = getSystemService(Context.ALARM_SERVICE) as AlarmManager
        val intent = Intent(this, WatchdogAlarmReceiver::class.java).apply {
            action = ACTION_RESTART
        }
        val pendingIntent = PendingIntent.getBroadcast(
            this, ALARM_REQUEST_CODE, intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        val triggerAt = SystemClock.elapsedRealtime() + CHECK_INTERVAL_MS
        try {
            alarmManager.set(AlarmManager.ELAPSED_REALTIME_WAKEUP, triggerAt, pendingIntent)
        } catch (_: SecurityException) {}
    }

    private fun isMainProcessDead(): Boolean {
        return try {
            val activityManager = getSystemService(Context.ACTIVITY_SERVICE) as ActivityManager
            val runningProcesses = activityManager.runningAppProcesses ?: return false
            runningProcesses.none { it.processName == packageName }
        } catch (e: Exception) {
            false
        }
    }

    private fun wasRiderOnline(): Boolean {
        for (prefName in listOf(packageName, "FlutterSharedPreferences", "${packageName}_preferences")) {
            try {
                val prefs = getSharedPreferences(prefName, Context.MODE_PRIVATE)
                if (prefs.contains("rider_is_online")) {
                    return prefs.getBoolean("rider_is_online", false)
                }
            } catch (_: Exception) {}
        }
        return false
    }

    private fun restartMainProcess() {
        try {
            val launchIntent = packageManager.getLaunchIntentForPackage(packageName) ?: return
            launchIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            launchIntent.putExtra("auto_restart", true)
            startActivity(launchIntent)
        } catch (_: Exception) {}
    }
}

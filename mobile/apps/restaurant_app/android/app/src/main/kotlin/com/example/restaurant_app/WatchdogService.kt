package com.example.restaurant_app

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
        private const val CHECK_INTERVAL_MS = 30000L
        private const val ALARM_REQUEST_CODE = 7777
        const val ACTION_RESTART = "com.example.restaurant_app.RESTART_BACKGROUND_SERVICE"
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
            ACTION_RESTART -> restartApp()
        }
        scheduleAlarm()
        return START_STICKY
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onTaskRemoved(rootIntent: Intent?) {
        scheduleAlarm()
        stopSelf()
    }

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
            .setContentTitle("UtterLazy Partner")
            .setContentText("Service is running")
            .setSmallIcon(android.R.drawable.ic_dialog_info)
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
            alarmManager.setAlarmClock(AlarmManager.AlarmClockInfo(triggerAt, null), pendingIntent)
        } catch (_: SecurityException) {
            alarmManager.set(AlarmManager.ELAPSED_REALTIME_WAKEUP, triggerAt, pendingIntent)
        }
    }

    private fun restartApp() {
        try {
            startActivity(
                packageManager.getLaunchIntentForPackage(packageName)
                    ?.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK) ?: return
            )
        } catch (_: Exception) {}
    }
}

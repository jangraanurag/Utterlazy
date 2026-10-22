package com.example.rider_app

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.net.Uri
import android.os.Build
import android.os.PowerManager
import androidx.core.app.NotificationCompat
import android.util.Log
import kotlin.math.roundToInt

class UtterLazyMessagingService : com.google.firebase.messaging.FirebaseMessagingService() {

    companion object {
        private const val TAG = "RiderFCM"
        private const val CHANNEL_ID = "rider_orders_channel"
        private const val CHANNEL_NAME = "Rider Delivery Alerts"
        private const val FALLBACK_CHANNEL_ID = "rider_fcm_fallback"
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d(TAG, "FCM token refreshed: ${token.take(20)}...")
    }

    override fun onMessageReceived(message: com.google.firebase.messaging.RemoteMessage) {
        super.onMessageReceived(message)
        Log.d(TAG, "FCM received: ${message.data}")

        val data = message.data
        if (data.isEmpty()) return

        val event = data["event"] ?: data["type"] ?: ""

        if (event in listOf(
                "rider.assigned", "rider_assigned", "new_assignment",
                "RiderAssigned", "App\\Events\\RiderAssigned"
            )
        ) {
            val orderId = data["order_id"] ?: data["id"] ?: return
            val deliveryFee = data["delivery_fee"] ?: "45"
            val orderIdShort = if (orderId.length > 8) orderId.substring(0, 8).uppercase() else orderId.uppercase()

            // Build distance/ETA info from FCM data
            val riderDistKm = data["rider_to_restaurant_distance_km"]
            val riderEta = data["rider_to_restaurant_eta_min"]
            val bodyText = buildString {
                append("Order #$orderIdShort — ₹$deliveryFee")
                if (riderDistKm != null || riderEta != null) {
                    val distStr = riderDistKm?.let {
                        val d = it.toDoubleOrNull() ?: 0.0
                        if (d < 1) "${(d * 1000).roundToInt()}m" else "${d}km"
                    } ?: ""
                    val etaStr = riderEta?.let { "~${it}min" } ?: ""
                    append("  •  $distStr $etaStr")
                }
            }

            wakeUpScreen()
            showIncomingDeliveryNotification(orderId, orderIdShort, deliveryFee, bodyText)
            launchAppWithData(data)
        }

        // Also handle notification+data messages: show as fallback
        val notificationBody = message.notification?.body
        if (notificationBody != null && data.isEmpty().not()) {
            val orderId = data["order_id"] ?: data["id"] ?: ""
            if (orderId.isNotEmpty()) {
                showFallbackNotification(notificationBody, orderId)
            }
        }
    }

    private fun wakeUpScreen() {
        try {
            val powerManager = getSystemService(Context.POWER_SERVICE) as PowerManager
            val wakeLock = powerManager.newWakeLock(
                PowerManager.FULL_WAKE_LOCK or PowerManager.ACQUIRE_CAUSES_WAKEUP or PowerManager.ON_AFTER_RELEASE,
                "utterlazy:rider_wakeup"
            )
            wakeLock.acquire(10 * 1000L)
        } catch (e: Exception) {
            Log.e(TAG, "Wake up failed: ${e.message}")
        }
    }

    private fun showIncomingDeliveryNotification(
        orderId: String,
        orderIdShort: String,
        deliveryFee: String,
        bodyText: String
    ) {
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        val ringtoneUri = Uri.parse("android.resource://${packageName}/raw/order_ringtone")

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val audioAttributes = AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_ALARM)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build()

            val channel = NotificationChannel(
                CHANNEL_ID,
                CHANNEL_NAME,
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "New delivery request alerts"
                enableVibration(true)
                enableLights(true)
                lockscreenVisibility = android.app.Notification.VISIBILITY_PUBLIC
                setSound(ringtoneUri, audioAttributes)
                setShowBadge(true)
            }
            notificationManager.createNotificationChannel(channel)
        }

        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            putExtra("screen", "assignment_detail")
            putExtra("order_id", orderId)
        }

        val pendingIntent = PendingIntent.getActivity(
            this,
            orderId.hashCode(),
            intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(com.khaalo.rider_app.R.mipmap.ic_launcher)
            .setContentTitle("New Delivery Request!")
            .setContentText(bodyText)
            .setStyle(NotificationCompat.BigTextStyle().bigText(bodyText))
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setFullScreenIntent(pendingIntent, true)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setVibrate(longArrayOf(0, 500, 200, 500, 200, 1000))
            .setSound(ringtoneUri)
            .setDefaults(0)
            .setOngoing(true)
            .setOnlyAlertOnce(false)
            .setShowWhen(true)
            .setTimeoutAfter(120000)
            .build()

        notificationManager.notify(orderId.hashCode(), notification)
    }

    private fun showFallbackNotification(body: String, orderId: String) {
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                FALLBACK_CHANNEL_ID,
                "Delivery Updates",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Fallback delivery notifications"
                enableVibration(true)
                lockscreenVisibility = android.app.Notification.VISIBILITY_PUBLIC
            }
            notificationManager.createNotificationChannel(channel)
        }

        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            putExtra("order_id", orderId)
        }

        val pendingIntent = PendingIntent.getActivity(
            this, orderId.hashCode() + 1, intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        val notification = NotificationCompat.Builder(this, FALLBACK_CHANNEL_ID)
            .setSmallIcon(com.khaalo.rider_app.R.mipmap.ic_launcher)
            .setContentTitle("Delivery Update")
            .setContentText(body)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .build()

        notificationManager.notify(orderId.hashCode() + 1000000, notification)
    }

    private fun launchAppWithData(data: Map<String, String>) {
        try {
            val intent = Intent(this, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                data.forEach { (key, value) -> putExtra(key, value) }
            }
            startActivity(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Launch app failed: ${e.message}")
        }
    }
}

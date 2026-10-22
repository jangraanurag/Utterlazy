package com.example.restaurant_app

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.net.Uri
import android.os.Build
import android.os.PowerManager
import androidx.core.app.NotificationCompat
import android.util.Log

class UtterLazyMessagingService : com.google.firebase.messaging.FirebaseMessagingService() {

    companion object {
        private const val TAG = "VendorFCM"
        private const val CHANNEL_ID = "vendor_orders_channel"
        private const val CHANNEL_NAME = "Vendor Order Alerts"
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
                "order.new", "new_order", "NewOrderForVendor",
                "App\\Events\\NewOrderForVendor"
            )
        ) {
            val orderId = data["order_id"] ?: data["id"] ?: return
            val grandTotal = data["grand_total"] ?: data["total_amount"] ?: "0"
            val orderIdShort = if (orderId.length > 8) orderId.substring(0, 8).uppercase() else orderId.uppercase()

            wakeUpScreen()
            showIncomingOrderNotification(orderId, orderIdShort, grandTotal)
            launchAppWithData(data)
        }
    }

    private fun wakeUpScreen() {
        try {
            val powerManager = getSystemService(Context.POWER_SERVICE) as PowerManager
            val wakeLock = powerManager.newWakeLock(
                PowerManager.FULL_WAKE_LOCK or PowerManager.ACQUIRE_CAUSES_WAKEUP or PowerManager.ON_AFTER_RELEASE,
                "utterlazy:vendor_wakeup"
            )
            wakeLock.acquire(10 * 1000L)
        } catch (e: Exception) {
            Log.e(TAG, "Wake up failed: ${e.message}")
        }
    }

    private fun showIncomingOrderNotification(
        orderId: String,
        orderIdShort: String,
        grandTotal: String
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
                description = "Incoming order alerts with accept/reject"
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
            putExtra("screen", "incoming_order")
            putExtra("order_id", orderId)
        }

        val pendingIntent = PendingIntent.getActivity(
            this,
            orderId.hashCode(),
            intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info) // will use launcher_icon fallback
            .setContentTitle("New Order Received!")
            .setContentText("Order #$orderIdShort — ₹$grandTotal. Tap to accept.")
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
            .build()

        notificationManager.notify(orderId.hashCode(), notification)
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

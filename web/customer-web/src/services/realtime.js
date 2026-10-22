// Real-time polling service for order tracking
// In production, replace with WebSocket/SSE

class RealtimeService {
  constructor() {
    this.intervals = new Map()
  }

  // Poll order status every N seconds
  startOrderPolling(orderId, callback, intervalMs = 10000) {
    if (this.intervals.has(`order_${orderId}`)) {
      this.stopOrderPolling(orderId)
    }

    const interval = setInterval(async () => {
      try {
        const { getOrder } = await import('../api.js')
        const data = await getOrder(orderId)
        callback(data.order)
      } catch (e) {
        console.error('Polling error:', e)
      }
    }, intervalMs)

    this.intervals.set(`order_${orderId}`, interval)
    return interval
  }

  stopOrderPolling(orderId) {
    const key = `order_${orderId}`
    if (this.intervals.has(key)) {
      clearInterval(this.intervals.get(key))
      this.intervals.delete(key)
    }
  }

  // Poll vendor dashboard every N seconds
  startVendorPolling(callback, intervalMs = 15000) {
    if (this.intervals.has('vendor_dashboard')) {
      this.stopVendorPolling()
    }

    const interval = setInterval(async () => {
      try {
        const { getActiveOrders } = await import('../api.js')
        const data = await getActiveOrders()
        callback(data)
      } catch (e) {
        console.error('Vendor polling error:', e)
      }
    }, intervalMs)

    this.intervals.set('vendor_dashboard', interval)
    return interval
  }

  stopVendorPolling() {
    if (this.intervals.has('vendor_dashboard')) {
      clearInterval(this.intervals.get('vendor_dashboard'))
      this.intervals.delete('vendor_dashboard')
    }
  }

  // Poll admin dashboard
  startAdminPolling(callback, intervalMs = 30000) {
    if (this.intervals.has('admin_dashboard')) {
      this.stopAdminPolling()
    }

    const interval = setInterval(async () => {
      try {
        const { getStats } = await import('../api.js')
        const data = await getStats()
        callback(data)
      } catch (e) {
        console.error('Admin polling error:', e)
      }
    }, intervalMs)

    this.intervals.set('admin_dashboard', interval)
    return interval
  }

  stopAdminPolling() {
    if (this.intervals.has('admin_dashboard')) {
      clearInterval(this.intervals.get('admin_dashboard'))
      this.intervals.delete('admin_dashboard')
    }
  }

  // Cleanup all polling
  stopAll() {
    this.intervals.forEach((interval) => clearInterval(interval))
    this.intervals.clear()
  }
}

export const realtime = new RealtimeService()
export default realtime

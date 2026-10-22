// WebSocket client for real-time updates
// Uses socket.io-client for Laravel Reverb/Pusher compatibility

class WebSocketClient {
  constructor(config) {
    this.url = config.url || 'wss://api.utterlazy.com'
    this.token = config.token
    this.socket = null
    this.listeners = new Map()
    this.reconnectAttempts = 0
    this.maxReconnectAttempts = 10
    this.reconnectDelay = 1000
  }

  connect() {
    // In production, use socket.io-client or native WebSocket
    // For now, we use polling fallback via the realtime.js service
    console.log(`WebSocket: Would connect to ${this.url}`)
    this.reconnectAttempts = 0
  }

  disconnect() {
    if (this.socket) {
      this.socket.close()
      this.socket = null
    }
    this.listeners.clear()
  }

  subscribe(channel, callback) {
    if (!this.listeners.has(channel)) {
      this.listeners.set(channel, [])
    }
    this.listeners.get(channel).push(callback)
    console.log(`WebSocket: Subscribed to ${channel}`)
  }

  unsubscribe(channel) {
    this.listeners.delete(channel)
    console.log(`WebSocket: Unsubscribed from ${channel}`)
  }

  on(event, callback) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, [])
    }
    this.listeners.get(event).push(callback)
    return () => this.off(event, callback)
  }

  off(event, callback) {
    if (this.listeners.has(event)) {
      const cbs = this.listeners.get(event).filter(cb => cb !== callback)
      this.listeners.set(event, cbs)
    }
  }

  emit(event, data) {
    if (this.socket && this.socket.readyState === WebSocket.OPEN) {
      this.socket.send(JSON.stringify({ event, data }))
    }
  }

  // Handle incoming messages
  handleMessage(message) {
    try {
      const parsed = JSON.parse(message)
      const { event, data } = parsed

      // Notify channel subscribers
      if (this.listeners.has(data.channel)) {
        this.listeners.get(data.channel).forEach(cb => cb(data))
      }

      // Notify event subscribers
      if (this.listeners.has(event)) {
        this.listeners.get(event).forEach(cb => cb(data))
      }
    } catch (e) {
      console.error('WebSocket parse error:', e)
    }
  }

  // Auto-reconnect on disconnect
  handleDisconnect() {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts)
      console.log(`WebSocket: Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts + 1})`)
      setTimeout(() => {
        this.reconnectAttempts++
        this.connect()
      }, delay)
    }
  }
}

// Singleton instance
let wsInstance = null

export const createWebSocket = (config) => {
  if (!wsInstance) {
    wsInstance = new WebSocketClient(config)
  }
  return wsInstance
}

export const getWebSocket = () => wsInstance

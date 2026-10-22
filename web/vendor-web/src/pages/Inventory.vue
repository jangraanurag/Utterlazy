<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'

const items = ref([])
const loading = ref(true)
const showAdd = ref(false)
const newItem = ref({ name: '', stock_quantity: '', unit: 'kg', low_stock_threshold: '', cost_per_unit: '' })

async function fetchInventory() {
  loading.value = true
  try {
    const res = await api.get('/vendor/inventory')
    items.value = res.data.items || res.data || []
  } catch (e) {
    items.value = []
  }
  loading.value = false
}

onMounted(fetchInventory)

async function addItem() {
  try {
    await api.post('/vendor/inventory', newItem.value)
    showAdd.value = false
    newItem.value = { name: '', stock_quantity: '', unit: 'kg', low_stock_threshold: '', cost_per_unit: '' }
    fetchInventory()
  } catch (e) { alert('Failed') }
}

const lowStockCount = () => items.value.filter(i => i.stock_quantity <= i.low_stock_threshold).length
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Inventory</h1>
        <p class="page-subtitle">{{ lowStockCount() }} items low on stock</p>
      </div>
      <button class="btn btn-primary" @click="showAdd = true">+ Add Item</button>
    </div>

    <div v-if="loading" class="text-center" style="padding: 60px;">Loading...</div>
    <div v-else-if="items.length === 0" class="card text-center" style="padding: 60px;">
      <div style="font-size: 3rem; margin-bottom: 12px;">📦</div>
      <p class="text-muted">No inventory items</p>
    </div>
    <div v-else class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Item</th><th>Stock</th><th>Min Alert</th><th>Cost/Unit</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td style="font-weight: 700;">{{ item.name }}</td>
            <td :style="{ color: item.stock_quantity <= item.low_stock_threshold ? 'var(--danger)' : '', fontWeight: item.stock_quantity <= item.low_stock_threshold ? '700' : '' }">
              {{ item.stock_quantity }} {{ item.unit }}
            </td>
            <td class="text-muted">{{ item.low_stock_threshold }} {{ item.unit }}</td>
            <td>₹{{ item.cost_per_unit }}</td>
            <td>
              <span class="badge" :class="item.stock_quantity <= item.low_stock_threshold ? 'badge-danger' : 'badge-success'">
                {{ item.stock_quantity <= item.low_stock_threshold ? 'LOW' : 'OK' }}
              </span>
            </td>
            <td><button class="btn btn-xs btn-secondary">Edit</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showAdd" class="modal-overlay" @click="showAdd = false">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <span class="modal-title">Add Inventory Item</span>
          <button class="modal-close" @click="showAdd = false">×</button>
        </div>
        <form @submit.prevent="addItem" class="p-6">
          <div class="form-group">
            <label class="form-label">Name</label>
            <input class="form-input" v-model="newItem.name" required placeholder="e.g. Chicken" />
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Stock</label>
              <input type="number" class="form-input" v-model="newItem.stock_quantity" required placeholder="15" />
            </div>
            <div class="form-group">
              <label class="form-label">Unit</label>
              <select class="form-input" v-model="newItem.unit">
                <option value="kg">kg</option><option value="g">g</option><option value="litre">litre</option><option value="ml">ml</option><option value="pcs">pcs</option><option value="packet">packet</option>
              </select>
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Min Stock Alert</label>
              <input type="number" class="form-input" v-model="newItem.low_stock_threshold" placeholder="5" />
            </div>
            <div class="form-group">
              <label class="form-label">Cost/Unit (₹)</label>
              <input type="number" step="0.01" class="form-input" v-model="newItem.cost_per_unit" placeholder="200" />
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-4 w-full">Add Item</button>
        </form>
      </div>
    </div>
  </div>
</template>

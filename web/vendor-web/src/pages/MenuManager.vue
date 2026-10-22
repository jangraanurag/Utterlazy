<script setup>
import { ref, onMounted, computed } from 'vue'
import { getMenu, addMenuItem, editMenuItem, removeMenuItem, addCategory, removeCategory } from '../api'

const categories = ref([])
const uncategorized = ref([])
const totalItems = ref(0)
const activeItems = ref(0)
const loading = ref(true)
const showAddItem = ref(false)
const showAddCat = ref(false)
const editingItem = ref(null)

const newItem = ref({ name: '', price: '', description: '', category_id: '', is_available: true, is_mrp: false, gst_percent: 5, hsn_code: '2106', tax_type: 'inclusive' })
const newCat = ref({ name: '', description: '' })

async function fetchMenu() {
  try {
    const data = await getMenu()
    categories.value = data.categories || []
    uncategorized.value = data.uncategorized || []
    totalItems.value = data.total_items || 0
    activeItems.value = data.active_items || 0
  } catch (e) { console.error(e) }
  loading.value = false
}

onMounted(fetchMenu)

async function handleAddItem() {
  try {
    await addMenuItem({ ...newItem.value, price: parseFloat(newItem.value.price) })
    newItem.value = { name: '', price: '', description: '', category_id: '', is_available: true, is_mrp: false, gst_percent: 5, hsn_code: '2106', tax_type: 'inclusive' }
    showAddItem.value = false
    fetchMenu()
  } catch (e) { alert(e.response?.data?.message || 'Failed') }
}

async function handleToggle(item) {
  try { await editMenuItem(item.id, { is_available: !item.is_available }); fetchMenu() }
  catch (e) { console.error(e) }
}

async function handleDelete(id) {
  if (!confirm('Delete this item?')) return
  try { await removeMenuItem(id); fetchMenu() }
  catch (e) { alert('Failed') }
}

async function handleAddCategory() {
  try { await addCategory(newCat.value); newCat.value = { name: '', description: '' }; showAddCat.value = false; fetchMenu() }
  catch (e) { alert('Failed') }
}

async function handleDeleteCategory(id) {
  if (!confirm('Delete this category?')) return
  try { await removeCategory(id); fetchMenu() }
  catch (e) { alert('Failed') }
}

async function handleUpdatePrice(item, newPrice) {
  try { await editMenuItem(item.id, { price: parseFloat(newPrice) }); editingItem.value = null; fetchMenu() }
  catch (e) { alert('Failed') }
}

const allItems = computed(() => [...uncategorized.value, ...categories.value.flatMap(c => c.menu_items || [])])
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Menu</h1>
        <p class="font-space" style="color: var(--text-secondary);">Total: {{ totalItems }} | Active: {{ activeItems }}</p>
      </div>
      <div class="flex gap-4">
        <button class="btn btn-secondary" @click="showAddCat = true">Add Category</button>
        <button class="btn btn-primary" @click="showAddItem = true">Add Item</button>
      </div>
    </div>

    <div class="grid-kpi" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 40px;">
      <div class="card kpi-card">
        <span class="kpi-label" style="color: var(--sovereign-teal);">TOTAL</span>
        <div class="kpi-value">{{ totalItems }}</div>
        <span style="font-size: 0.7rem; color: var(--text-muted);">Listed items</span>
      </div>
      <div class="card kpi-card">
        <span class="kpi-label" style="color: var(--success);">ACTIVE</span>
        <div class="kpi-value">{{ activeItems }}</div>
        <span style="font-size: 0.7rem; color: var(--text-muted);">Live on platform</span>
      </div>
      <div class="card kpi-card">
        <span class="kpi-label" style="color: var(--sovereign-orange);">GST</span>
        <div class="kpi-value">5%</div>
        <span style="font-size: 0.7rem; color: var(--text-muted);">Standard rate</span>
      </div>
    </div>

    <div v-if="loading" class="text-center" style="padding: 100px;">
      <div class="spinner" style="margin: 0 auto;"></div>
      <p class="font-space mt-4">Loading...</p>
    </div>
    <div v-else style="display: grid; gap: 32px;">
      <div v-for="cat in categories" :key="cat.id">
        <div class="flex-between mb-4" style="border-bottom: 1px solid var(--glass-edge); padding-bottom: 8px;">
          <h3 class="font-space" style="font-size: 1.2rem; font-weight: 700;">
            {{ cat.name.toUpperCase() }}
            <span class="badge badge-info" style="margin-left: 16px; font-size: 0.7rem;">{{ cat.menu_items?.length || 0 }}</span>
          </h3>
          <button class="btn btn-xs btn-secondary" style="color: var(--danger);" @click="handleDeleteCategory(cat.id)">Delete</button>
        </div>
        <div style="display: grid; gap: 12px;">
          <div v-for="item in (cat.menu_items || [])" :key="item.id" class="order-card" style="padding: 16px 24px; align-items: center;">
            <div class="flex gap-6 items-center" style="flex: 1;">
              <label class="switch">
                <input type="checkbox" :checked="item.is_available" @change="handleToggle(item)" />
                <span class="slider"></span>
              </label>
              <div style="flex: 1;">
                <div class="flex gap-3 items-center">
                  <span class="font-space" style="font-weight: 700; font-size: 1.1rem;">{{ item.name.toUpperCase() }}</span>
                  <span v-if="item.is_mrp" class="badge badge-info" style="font-size: 0.6rem;">MRP</span>
                  <span class="badge badge-warning" style="font-size: 0.6rem;">GST {{ item.gst_percent }}%</span>
                </div>
                <p class="font-space mt-1" style="font-size: 0.75rem; color: var(--text-muted);">
                  HSN: {{ item.hsn_code || 'NA' }} | {{ item.tax_type?.toUpperCase() }}
                </p>
              </div>
            </div>
            <div class="flex gap-6 items-center">
              <div class="text-right">
                <input v-if="editingItem === item.id" type="number" class="form-input" style="width: 100px; text-align: right;" :default-value="item.price" autofocus @blur="handleUpdatePrice(item, $event.target.value)" @keydown.enter="handleUpdatePrice(item, $event.target.value)" />
                <span v-else class="font-space" style="font-size: 1.4rem; font-weight: 700; color: var(--sovereign-orange); cursor: pointer;" @click="editingItem = item.id">₹{{ item.price }}</span>
              </div>
              <button class="btn btn-xs btn-secondary" style="color: var(--danger);" @click="handleDelete(item.id)">🗑</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="allItems.length === 0" class="card text-center" style="padding: 100px;">
        <div style="font-size: 4rem; margin-bottom: 24px; opacity: 0.2;">📦</div>
        <p class="font-space" style="color: var(--text-muted); font-size: 1.2rem;">No items yet</p>
      </div>
    </div>

    <div v-if="showAddItem" class="modal-overlay" @click="showAddItem = false">
      <div class="modal modal-lg" @click.stop>
        <div class="modal-header">
          <span class="modal-title font-space">Add New Item</span>
          <button class="modal-close" @click="showAddItem = false">×</button>
        </div>
        <form @submit.prevent="handleAddItem" class="p-6">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Item Name</label>
              <input class="form-input" v-model="newItem.name" required placeholder="e.g. Butter Chicken" />
            </div>
            <div class="form-group">
              <label class="form-label">Price (₹)</label>
              <input type="number" step="0.01" class="form-input" v-model="newItem.price" required placeholder="499" />
            </div>
          </div>
          <div class="grid-3 mt-4">
            <div class="form-group">
              <label class="form-label">GST Rate</label>
              <select class="form-input" v-model="newItem.gst_percent">
                <option value="5">5%</option><option value="12">12%</option><option value="18">18%</option><option value="0">0%</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">HSN Code</label>
              <input class="form-input" v-model="newItem.hsn_code" placeholder="2106" />
            </div>
            <div class="form-group">
              <label class="form-label">Tax Type</label>
              <select class="form-input" v-model="newItem.tax_type">
                <option value="inclusive">Inclusive</option><option value="exclusive">Exclusive</option>
              </select>
            </div>
          </div>
          <div class="form-group mt-4">
            <label class="form-label">Category</label>
            <select class="form-input" v-model="newItem.category_id">
              <option value="">Uncategorized</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="form-group mt-4">
            <label class="form-label">Description</label>
            <textarea class="form-input" rows="2" v-model="newItem.description" placeholder="Description..."></textarea>
          </div>
          <div class="flex gap-8 mb-8 mt-6">
            <label class="flex gap-3 items-center">
              <input type="checkbox" v-model="newItem.is_available" /> Available
            </label>
            <label class="flex gap-3 items-center">
              <input type="checkbox" v-model="newItem.is_mrp" /> MRP Item
            </label>
          </div>
          <button type="submit" class="btn btn-primary w-full">Add Item</button>
        </form>
      </div>
    </div>

    <div v-if="showAddCat" class="modal-overlay" @click="showAddCat = false">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <span class="modal-title font-space">Add Category</span>
          <button class="modal-close" @click="showAddCat = false">×</button>
        </div>
        <form @submit.prevent="handleAddCategory" class="p-6">
          <div class="form-group">
            <label class="form-label">Category Name</label>
            <input class="form-input" v-model="newCat.name" required placeholder="e.g. Main Course" />
          </div>
          <button type="submit" class="btn btn-primary mt-4 w-full">Create</button>
        </form>
      </div>
    </div>
  </div>
</template>

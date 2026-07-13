<template>
  <section aria-labelledby="deliveries-heading">
    <div class="nm-toolbar">
      <div><h2 id="deliveries-heading">Delivery activity</h2><p>{{ store.deliveryTotal }} recorded deliveries</p></div>
      <button class="nm-icon-button" type="button" title="Refresh delivery activity" :disabled="store.loadingDeliveries" @click="load"><RefreshCw :class="{ spin: store.loadingDeliveries }" aria-hidden="true" /><span class="sr-only">Refresh delivery activity</span></button>
    </div>
    <div v-if="store.deliveryOverview" class="nm-delivery-summary" aria-label="Email activity in the last 24 hours">
      <span><strong>{{ store.deliveryOverview.total }}</strong> Total</span>
      <span><strong>{{ store.deliveryOverview.queued }}</strong> Queued</span>
      <span><strong>{{ store.deliveryOverview.sent }}</strong> Sent</span>
      <span><strong>{{ store.deliveryOverview.failed }}</strong> Failed</span>
    </div>
    <form class="nm-filters" role="search" @submit.prevent="search">
      <label><span>Search</span><input v-model="filters.search" type="search" placeholder="Subject or event key" /></label>
      <label><span>Status</span><select v-model="filters.status"><option value="">All statuses</option><option value="queued">Queued</option><option value="sent">Sent</option><option value="failed">Failed</option></select></label>
      <label><span>Event key</span><input v-model="filters.event_key" /></label>
      <button class="nm-button primary" type="submit" :disabled="store.loadingDeliveries">Apply</button>
      <button class="nm-button secondary" type="button" :disabled="store.loadingDeliveries" @click="clearFilters">Clear</button>
    </form>

    <div v-if="store.loadingDeliveries && !store.deliveries.length" class="nm-state">Loading delivery activity...</div>
    <div v-else-if="store.deliveryError" class="nm-state nm-state-error" role="alert">{{ store.deliveryError }} <button type="button" @click="load">Retry</button></div>
    <div v-else-if="!store.deliveries.length" class="nm-state">No deliveries match the current filters.</div>
    <div v-else class="nm-table-wrap nm-delivery-table">
      <table>
        <caption class="sr-only">Notification delivery activity with masked recipients</caption>
        <thead><tr><th scope="col">Created</th><th scope="col">Event</th><th scope="col">Recipient</th><th scope="col">Subject</th><th scope="col">Status</th><th scope="col">Attempts</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <tr v-for="delivery in store.deliveries" :key="delivery.id">
            <td><time :datetime="delivery.created_at">{{ formatDate(delivery.created_at) }}</time></td>
            <td><code>{{ delivery.event_key || 'unmapped' }}</code><span v-if="delivery.is_test" class="nm-badge neutral">Test</span></td>
            <td>{{ delivery.recipient }}</td>
            <td><strong>{{ delivery.subject }}</strong><small v-if="delivery.failure_reason">{{ delivery.failure_reason }}</small></td>
            <td><span class="nm-badge" :class="delivery.status">{{ delivery.status }}</span></td>
            <td>{{ delivery.attempts }}</td>
            <td><button v-if="delivery.status === 'failed'" class="nm-icon-button" type="button" title="Retry delivery" @click="retry(delivery.id)"><RotateCcw aria-hidden="true" /><span class="sr-only">Retry delivery</span></button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <nav v-if="store.deliveryLastPage > 1" class="nm-pagination" aria-label="Delivery pages">
      <button class="nm-icon-button" type="button" title="Previous page" :disabled="filters.page <= 1 || store.loadingDeliveries" @click="changePage(filters.page - 1)"><ChevronLeft aria-hidden="true" /><span class="sr-only">Previous page</span></button>
      <span>Page {{ filters.page }} of {{ store.deliveryLastPage }}</span>
      <button class="nm-icon-button" type="button" title="Next page" :disabled="filters.page >= store.deliveryLastPage || store.loadingDeliveries" @click="changePage(filters.page + 1)"><ChevronRight aria-hidden="true" /><span class="sr-only">Next page</span></button>
    </nav>
  </section>
</template>

<script setup lang="ts">
import { onMounted, reactive } from 'vue'
import { ChevronLeft, ChevronRight, RefreshCw, RotateCcw } from 'lucide-vue-next'
import { useNotificationManagementStore } from '@/store/notifications'
import type { DeliveryFilters } from '@/types/notifications'

const store = useNotificationManagementStore()
const filters = reactive<DeliveryFilters>({ status: '', event_key: '', search: '', page: 1, per_page: 25 })
const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
async function load() { try { await Promise.all([store.fetchDeliveries({ ...filters }), store.fetchDeliveryOverview()]) } catch { /* Store exposes the error. */ } }
async function retry(id: number) { await store.retryDelivery(id); await load() }
function search() { filters.page = 1; load() }
function clearFilters() { filters.status = ''; filters.event_key = ''; filters.search = ''; filters.page = 1; load() }
function changePage(page: number) { filters.page = page; load() }
onMounted(load)
</script>

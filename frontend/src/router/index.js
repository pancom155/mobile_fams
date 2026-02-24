import { createRouter, createWebHistory } from 'vue-router'
import NewTransactionPage from '../pages/NewTransactionPage.vue'
import AssetCountPage from '../pages/AssetCountPage.vue'
import TransactionsPage from '../pages/TransactionsPage.vue'

const routes = [
  {
    path: '/',
    redirect: '/new-transaction'
  },
  {
    path: '/new-transaction',
    component: NewTransactionPage,
    alias: '/new'
  },
  {
    path: '/asset-count',
    component: AssetCountPage
  },
  {
    path: '/transactions',
    component: TransactionsPage
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router

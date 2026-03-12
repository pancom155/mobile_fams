import { createRouter, createWebHistory } from 'vue-router'
import NewTransactionPage from '../pages/NewTransactionPage.vue'
import AssetCountPage from '../pages/AssetCountPage.vue'
import TransactionsPage from '../pages/TransactionsPage.vue'
import LoginPage from '../pages/LoginPage.vue'

const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    component: LoginPage
  },
  {
    path: '/new-transaction',
    component: NewTransactionPage,
    alias: '/new',
    meta: { requiresAuth: true }
  },
  {
    path: '/asset-count',
    component: AssetCountPage,
    meta: { requiresAuth: true }
  },
  {
    path: '/transactions',
    component: TransactionsPage,
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation Guard
router.beforeEach((to, from, next) => {
  const isAuthenticated = !!localStorage.getItem('fams_token')
  
  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
  } else if (to.path === '/login' && isAuthenticated) {
    next('/transactions')
  } else {
    next()
  }
})

export default router

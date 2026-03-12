<template>
  <q-layout view="lHh Lpr lFf">
    <q-page-container>
      <q-page class="flex flex-center bg-grey-2">
        <q-card class="login-card shadow-24" style="width: 350px; border-radius: 15px;">
          <q-card-section class="bg-primary text-white q-pa-lg text-center">
            <q-avatar size="100px" font-size="52px" color="white" text-color="primary" icon="person" class="q-mb-md" />
            <div class="text-h5 text-weight-bold">FAMS Mobile</div>
            <div class="text-subtitle2">Inventory Management System</div>
          </q-card-section>

          <q-card-section class="q-pa-lg">
            <q-form @submit="onSubmit" class="q-gutter-md">
              <q-input
                v-model="email"
                label="Email Address"
                type="email"
                outlined
                rounded
                dense
                :rules="[val => !!val || 'Email is required']"
              >
                <template v-slot:prepend>
                  <q-icon name="email" color="primary" />
                </template>
              </q-input>

              <q-input
                v-model="password"
                label="Password"
                type="password"
                outlined
                rounded
                dense
                :rules="[val => !!val || 'Password is required']"
              >
                <template v-slot:prepend>
                  <q-icon name="lock" color="primary" />
                </template>
              </q-input>

              <div class="q-mt-lg">
                <q-btn
                  label="Login"
                  type="submit"
                  color="primary"
                  class="full-width text-weight-bold"
                  rounded
                  size="lg"
                  :loading="loading"
                  unelevated
                />
              </div>
            </q-form>
          </q-card-section>

          <q-card-section class="text-center text-grey-7 q-pt-none pb-lg">
            <small>Backend: {{ API_BASE_URL }}</small>
          </q-card-section>
        </q-card>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import axios from 'axios'
import { API_BASE_URL } from '../config/api'

const router = useRouter()
const $q = useQuasar()

const email = ref('')
const password = ref('')
const loading = ref(false)

const onSubmit = async () => {
  loading.value = true
  try {
    const response = await axios.post(`${API_BASE_URL}/api/login`, {
      email: email.value,
      password: password.value
    })

    if (response.data.token) {
      localStorage.setItem('fams_token', response.data.token)
      localStorage.setItem('fams_user', JSON.stringify(response.data.user))
      
      $q.notify({
        type: 'positive',
        message: 'Welcome back!',
        position: 'top'
      })
      
      router.push('/transactions')
    }
  } catch (error) {
    console.error('Login error:', error)
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || 'Login failed. Please check your connection.',
      position: 'top'
    })
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-card {
  border: none;
  background: white;
}
</style>

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User } from '@/types/auth';
import { router } from '@/app/router';
import { useFetch } from '@/composables/useFetch';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const isAuthenticated = computed(() => !!user.value);
  
  const { fetch, loading, error } = useFetch();
  
  async function login(credentials: { email: string; password: string }) {
    const response = await fetch('/api/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
    
    user.value = response.data.user;
    localStorage.setItem('auth_token', response.data.token);
    router.push('/dashboard');
  }
  
  async function register(payload: RegisterPayload) {
    const response = await fetch('/api/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    
    user.value = response.data.user;
    localStorage.setItem('auth_token', response.data.token);
    router.push('/dashboard');
  }
  
  async function logout() {
    await fetch('/api/logout', { method: 'POST' });
    user.value = null;
    localStorage.removeItem('auth_token');
    router.push('/login');
  }
  
  async function fetchUser() {
    try {
      const response = await fetch('/api/user');
      user.value = response.data;
    } catch (err) {
      user.value = null;
    }
  }
  
  return {
    user,
    isAuthenticated,
    loading,
    error,
    login,
    register,
    logout,
    fetchUser,
  };
});
<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const name = page.props.name;
const quote = page.props.quote;

defineProps<{
  title?: string;
  description?: string;
}>();
</script>

<template>
  <div
    class="relative min-h-screen grid grid-cols-1 lg:grid-cols-2 overflow-hidden bg-gradient-to-br from-zinc-900 via-zinc-950 to-black text-white"
  >
    <!-- Left Side (Brand / Quote) -->
    <div class="relative hidden lg:flex flex-col justify-between p-10">
      <!-- Overlay -->
      <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/30 via-purple-700/20 to-pink-600/20 backdrop-blur-xl" />

      <!-- Branding -->
      <Link
        :href="route('home')"
        class="relative z-20 flex items-center text-2xl font-bold tracking-tight"
      >
        <AppLogoIcon class="mr-3 size-10 text-indigo-400" />
        <span class="bg-gradient-to-r from-indigo-400 to-pink-500 bg-clip-text text-transparent">
          {{ name }}
        </span>
      </Link>

      <!-- Quote -->
      <div v-if="quote" class="relative z-20 mt-auto">
        <blockquote class="border-l-4 border-indigo-400 pl-4 italic">
          <p class="text-lg leading-relaxed">“{{ quote.message }}”</p>
          <footer class="mt-2 text-sm text-zinc-300">— {{ quote.author }}</footer>
        </blockquote>
      </div>
    </div>

    <!-- Right Side (Form / Content) -->
    <div class="relative flex items-center justify-center p-6 lg:p-12">
      <div
        class="w-full max-w-md bg-white/5 backdrop-blur-lg rounded-2xl shadow-2xl p-8 space-y-6 border border-white/10"
      >
        <!-- Header -->
        <div class="text-center space-y-2">
          <h1
            v-if="title"
            class="text-2xl font-semibold bg-gradient-to-r from-indigo-400 to-pink-500 bg-clip-text text-transparent"
          >
            {{ title }}
          </h1>
          <p v-if="description" class="text-sm text-zinc-400">
            {{ description }}
          </p>
        </div>

        <!-- Slot for Form -->
        <slot />

        <!-- Footer -->
        <div class="text-center text-xs text-zinc-500">
          © {{ new Date().getFullYear() }} {{ name }}. All rights reserved.
        </div>
      </div>
    </div>
  </div>
</template>

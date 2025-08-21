<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Tournament {
    id: number;
    title: string;
    description?: string;
    prize_pool: number;
    entry_fee: number;
    max_players: number;
    status: string;
    starts_at?: string;
    ends_at?: string;
    game?: {
        name: string;
    };
}

interface Props {
    featuredTournaments: Tournament[];
    ongoingTournaments: Tournament[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: '/',
    },
];

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
};

const formatDate = (dateString?: string) => {
    if (!dateString) return 'TBD';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'registration_open':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'in_progress':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'registration_closed':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const getStatusText = (status: string) => {
    return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>

<template>
    <Head title="Home" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 overflow-x-auto bg-gray-100">
            <!-- Featured Tournaments Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold tracking-tight text-white">Featured Tournaments</h2>
                </div>
                
                <div v-if="props.featuredTournaments.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="tournament in props.featuredTournaments"
                        :key="tournament.id"
                        class="relative overflow-hidden rounded-lg border border-sidebar-border/70 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-sidebar-border dark:bg-gray-800"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between">
                                <h3 class="font-semibold text-lg line-clamp-2">{{ tournament.title }}</h3>
                                <span 
                                    :class="getStatusColor(tournament.status)"
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                >
                                    {{ getStatusText(tournament.status) }}
                                </span>
                            </div>
                            
                            <p v-if="tournament.description" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                {{ tournament.description }}
                            </p>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Prize Pool:</span>
                                    <span class="font-medium text-green-600 dark:text-green-400">
                                        {{ formatCurrency(tournament.prize_pool) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Entry Fee:</span>
                                    <span class="font-medium">{{ formatCurrency(tournament.entry_fee) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Max Players:</span>
                                    <span class="font-medium">{{ tournament.max_players }}</span>
                                </div>
                                <div v-if="tournament.game" class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Game:</span>
                                    <span class="font-medium">{{ tournament.game.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Starts:</span>
                                    <span class="font-medium">{{ formatDate(tournament.starts_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-else class="text-center py-8">
                    <p class="text-gray-500 dark:text-gray-400">No featured tournaments available at the moment.</p>
                </div>
            </div>

            <!-- Ongoing Tournaments Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold tracking-tight">Ongoing Tournaments</h2>
                </div>
                
                <div v-if="props.ongoingTournaments.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="tournament in props.ongoingTournaments"
                        :key="tournament.id"
                        class="relative overflow-hidden rounded-lg border border-sidebar-border/70 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-sidebar-border dark:bg-gray-800"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between">
                                <h3 class="font-semibold text-lg line-clamp-2">{{ tournament.title }}</h3>
                                <span 
                                    :class="getStatusColor(tournament.status)"
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                >
                                    {{ getStatusText(tournament.status) }}
                                </span>
                            </div>
                            
                            <p v-if="tournament.description" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                {{ tournament.description }}
                            </p>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Prize Pool:</span>
                                    <span class="font-medium text-green-600 dark:text-green-400">
                                        {{ formatCurrency(tournament.prize_pool) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Entry Fee:</span>
                                    <span class="font-medium">{{ formatCurrency(tournament.entry_fee) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Max Players:</span>
                                    <span class="font-medium">{{ tournament.max_players }}</span>
                                </div>
                                <div v-if="tournament.game" class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Game:</span>
                                    <span class="font-medium">{{ tournament.game.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Started:</span>
                                    <span class="font-medium">{{ formatDate(tournament.starts_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-else class="text-center py-8">
                    <p class="text-gray-500 dark:text-gray-400">No ongoing tournaments at the moment.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

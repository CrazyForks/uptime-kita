<script setup lang="ts">
import WallboardLayout from '@/layouts/WallboardLayout.vue';
import type { Monitor, Tag } from '@/types/monitor';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Icon from '@/components/Icon.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import CompactDots from './partials/CompactDots.vue';
import CompactTable from './partials/CompactTable.vue';
import CompactBars from './partials/CompactBars.vue';
import CompactCards from './partials/CompactCards.vue';
import CompactDashboard from './partials/CompactDashboard.vue';

const props = defineProps<{
    monitors: { data: Monitor[] };
    availableTags: Tag[];
}>();

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);

// View State
const viewType = ref(localStorage.getItem('compact_view_type') || 'dots');
const groupBy = ref(localStorage.getItem('compact_group_by') || 'status');
const sortBy = ref(localStorage.getItem('compact_sort_by') || 'url');
const searchQuery = ref('');

watch(viewType, (val) => localStorage.setItem('compact_view_type', val));
watch(groupBy, (val) => localStorage.setItem('compact_group_by', val));
watch(sortBy, (val) => localStorage.setItem('compact_sort_by', val));

// Refresh logic
const countdown = ref(60);
let timer: number | null = null;

const startTimer = () => {
    timer = window.setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            router.reload({ only: ['monitors'] });
            countdown.value = 60;
        }
    }, 1000);
};

onMounted(() => startTimer());
onUnmounted(() => timer && clearInterval(timer));

// Filtering & Sorting
const filteredMonitors = computed(() => {
    let result = [...props.monitors.data];
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(m => 
            m.url.toLowerCase().includes(query) || 
            m.name.toLowerCase().includes(query) ||
            m.tags?.some(t => t.name.toLowerCase().includes(query))
        );
    }

    // Sort
    return result.sort((a, b) => {
        let diff = 0;
        if (sortBy.value === 'url') {
            diff = a.url.localeCompare(b.url);
        } else if (sortBy.value === 'status') {
            const statusOrder = { 'down': 0, 'up': 1, 'not yet checked': 2 };
            diff = (statusOrder[a.uptime_status] ?? 3) - (statusOrder[b.uptime_status] ?? 3);
        } else if (sortBy.value === 'latency') {
            const rtA = a.latest_history?.response_time ?? 999999;
            const rtB = b.latest_history?.response_time ?? 999999;
            diff = rtA - rtB;
        } else if (sortBy.value === 'uptime') {
            const upA = a.statistics?.uptime_30d ?? 0;
            const upB = b.statistics?.uptime_30d ?? 0;
            diff = upB - upA; // Default highest first
        }
        return diff;
    });
});

// Grouping
const groups = computed(() => {
    const data = filteredMonitors.value;
    if (groupBy.value === 'status') {
        return [
            { name: 'Down', monitors: data.filter(m => m.uptime_status === 'down'), color: 'text-red-500' },
            { name: 'Up', monitors: data.filter(m => m.uptime_status === 'up'), color: 'text-green-500' },
            { name: 'Other', monitors: data.filter(m => m.uptime_status !== 'up' && m.uptime_status !== 'down'), color: 'text-yellow-500' }
        ].filter(g => g.monitors.length > 0);
    }
    
    if (groupBy.value === 'tags') {
        const tagGroups = props.availableTags.map(tag => ({
            name: tag.name,
            monitors: data.filter(m => m.tags?.some(t => t.name === tag.name)),
            color: 'text-blue-500'
        })).filter(g => g.monitors.length > 0);
        
        const noTagMonitors = data.filter(m => !m.tags || m.tags.length === 0);
        if (noTagMonitors.length > 0) {
            tagGroups.push({ name: 'No Tag', monitors: noTagMonitors, color: 'text-gray-500' });
        }
        return tagGroups;
    }
    
    return [{ name: 'All Monitors', monitors: data, color: 'text-gray-900 dark:text-gray-100' }];
});
</script>

<template>
    <WallboardLayout>
        <Head title="Compact Monitors Wallboard" />

        <div class="mx-auto max-w-[1920px]">
            <!-- Wallboard Controls -->
            <div class="mb-8 flex flex-col gap-6">
                <!-- Top Row: Title & Stats -->
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b border-gray-100 dark:border-gray-900 pb-6">
                    <div class="flex items-center gap-4">
                        <Link :href="isAuthenticated ? route('dashboard') : route('home')" class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-900 dark:hover:bg-gray-800 transition-all border border-gray-200 dark:border-gray-800">
                            <Icon name="arrowLeft" size="20" />
                        </Link>
                        <div>
                            <div class="flex items-center gap-3">
                                <h1 class="text-2xl font-black tracking-tighter text-gray-900 dark:text-gray-100 uppercase">Status Wallboard</h1>
                                <span class="rounded-lg bg-blue-600 px-2 py-0.5 text-[10px] font-black text-white">LIVE</span>
                            </div>
                            <div class="mt-1 flex items-center gap-3 text-[10px] text-gray-500 uppercase tracking-widest font-bold">
                                <span class="flex items-center gap-1"><Icon name="monitor" size="10" /> {{ filteredMonitors.length }} Targets</span>
                                <span class="h-1 w-1 rounded-full bg-gray-300 dark:bg-gray-700"></span>
                                <span class="flex items-center gap-1 text-blue-500">
                                    <Icon name="clock" size="10" />
                                    REFRESH IN {{ countdown }}S
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden sm:flex flex-col items-end">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">System Time</span>
                            <span class="text-xs font-bold tabular-nums">{{ new Date().toLocaleTimeString() }}</span>
                        </div>
                        <Link
                            v-if="!isAuthenticated"
                            :href="route('login')"
                            class="h-10 rounded-xl bg-gray-900 px-6 flex items-center text-[10px] font-black uppercase tracking-[0.2em] text-white hover:bg-black transition-all dark:bg-white dark:text-black dark:hover:bg-gray-200 shadow-lg"
                        >
                            Login
                        </Link>
                    </div>
                </div>

                <!-- Bottom Row: Controls Grid (Sticky) -->
                <div class="sticky top-0 z-30 -mx-4 px-4 py-4 mb-2 bg-white/80 dark:bg-gray-950/80 backdrop-blur-xl border-b border-transparent transition-all duration-300 group-[.is-stuck]:border-gray-100 dark:group-[.is-stuck]:border-gray-900">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <!-- Search Box -->
                    <div class="md:col-span-4 flex flex-col gap-1.5">
                        <label class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 ml-1">Search & Filter</label>
                        <div class="relative">
                            <Input
                                v-model="searchQuery"
                                placeholder="FILTER BY DOMAIN, NAME OR TAG..."
                                class="h-10 rounded-xl border-none bg-gray-100 dark:bg-gray-900 px-10 text-[10px] font-bold uppercase tracking-widest focus:ring-2 focus:ring-blue-500 transition-all"
                            />
                            <Icon name="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" size="16" />
                        </div>
                    </div>

                    <!-- Switchers -->
                    <div class="md:col-span-8 flex flex-wrap gap-4 items-center justify-start md:justify-end">
                        <!-- View -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 ml-1">Layout</label>
                            <div class="flex rounded-xl bg-gray-100 p-1 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                <button
                                    v-for="type in ['dots', 'table', 'bars', 'cards', 'dashboard']"
                                    :key="type"
                                    @click="viewType = type"
                                    :class="[
                                        'flex h-8 w-10 items-center justify-center rounded-lg transition-all',
                                        viewType === type 
                                            ? 'bg-white text-blue-600 shadow-sm dark:bg-gray-800 dark:text-blue-400' 
                                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                                    ]"
                                    :title="type.toUpperCase() + ' VIEW'"
                                >
                                    <Icon :name="type === 'dots' ? 'layoutGrid' : type === 'table' ? 'list' : type === 'bars' ? 'columns' : type === 'cards' ? 'grid' : 'activity'" size="16" />
                                </button>
                            </div>
                        </div>

                        <!-- Grouping -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 ml-1">Group By</label>
                            <div class="flex rounded-xl bg-gray-100 p-1 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                <button
                                    v-for="group in ['status', 'tags', 'none']"
                                    :key="group"
                                    @click="groupBy = group"
                                    :class="[
                                        'h-8 rounded-lg px-4 text-[10px] font-bold uppercase tracking-widest transition-all',
                                        groupBy === group 
                                            ? 'bg-white text-blue-600 shadow-sm dark:bg-gray-800 dark:text-blue-400' 
                                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                                    ]"
                                >
                                    {{ group }}
                                </button>
                            </div>
                        </div>

                        <!-- Sorting -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 ml-1">Sort By</label>
                            <div class="flex rounded-xl bg-gray-100 p-1 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                <button
                                    v-for="sort in ['url', 'status', 'latency', 'uptime']"
                                    :key="sort"
                                    @click="sortBy = sort"
                                    :class="[
                                        'flex h-8 w-10 items-center justify-center rounded-lg transition-all',
                                        sortBy === sort 
                                            ? 'bg-white text-blue-600 shadow-sm dark:bg-gray-800 dark:text-blue-400' 
                                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                                    ]"
                                    :title="'Sort by ' + sort"
                                >
                                    <Icon v-if="sort === 'url'" name="caseSensitive" size="16" />
                                    <Icon v-else-if="sort === 'status'" name="checkCircle" size="16" />
                                    <Icon v-else-if="sort === 'latency'" name="zap" size="16" />
                                    <Icon v-else-if="sort === 'uptime'" name="trendingUp" size="16" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- End Sticky -->
            </div>

            <!-- Dashboard Grid -->
            <div class="space-y-12">
                <div v-for="group in groups" :key="group.name" class="animate-in fade-in slide-in-from-top-2 duration-500">
                    <div class="mb-4 flex items-center gap-3">
                        <h2 :class="['text-[10px] font-black uppercase tracking-[0.2em]', group.color]">
                            {{ group.name }}
                            <span class="ml-2 text-gray-500 font-bold">[{{ group.monitors.length }}]</span>
                        </h2>
                        <div class="h-px flex-1 bg-gray-100 dark:bg-gray-900/50"></div>
                    </div>

                    <component
                        :is="viewType === 'dots' ? CompactDots : viewType === 'table' ? CompactTable : viewType === 'bars' ? CompactBars : viewType === 'cards' ? CompactCards : CompactDashboard"
                        :monitors="group.monitors"
                    />
                </div>
                
                <div v-if="filteredMonitors.length === 0" class="flex flex-col items-center justify-center py-32 text-center">
                    <Icon name="searchX" size="64" class="mb-4 text-gray-200 dark:text-gray-800" />
                    <h3 class="text-xs font-black uppercase tracking-[0.3em] text-gray-400 dark:text-gray-600">No matching monitors found</h3>
                    <Button variant="outline" class="mt-6 border-gray-200 text-[10px] font-bold uppercase tracking-widest dark:border-gray-800" @click="searchQuery = ''">
                        RESET FILTER
                    </Button>
                </div>
            </div>
        </div>
    </WallboardLayout>
</template>

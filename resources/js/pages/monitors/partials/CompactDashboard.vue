<script setup lang="ts">
import type { Monitor } from '@/types/monitor';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/components/Icon.vue';

const props = defineProps<{
    monitors: Monitor[];
}>();

const getDomainFromUrl = (url: string) => {
    try {
        const domain = new URL(url).hostname;
        return domain.replace('www.', '');
    } catch {
        return url;
    }
};

const getResponseTimeColor = (ms: number | undefined) => {
    if (!ms) return 'text-gray-400';
    if (ms < 300) return 'text-green-500';
    if (ms < 800) return 'text-yellow-500';
    return 'text-red-500';
};

const getTrendIcon = (monitor: Monitor) => {
    const current = monitor.latest_history?.response_time;
    const avg = monitor.statistics?.avg_response_time_24h;
    
    if (!current || !avg) return null;
    if (current < avg * 0.9) return { name: 'trendingDown', color: 'text-green-500' };
    if (current > avg * 1.1) return { name: 'trendingUp', color: 'text-red-500' };
    return null;
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <div
            v-for="monitor in monitors"
            :key="monitor.id"
            class="group relative overflow-hidden rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-all hover:border-blue-200 hover:shadow-md dark:border-gray-800 dark:bg-gray-900/50 dark:hover:border-blue-900/50"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <Link :href="route('monitor.show', monitor.id)" class="group/link flex items-center gap-2">
                        <img v-if="monitor.favicon" :src="monitor.favicon" alt="" class="h-5 w-5 rounded-full" />
                        <h3 class="truncate text-sm font-bold tracking-tight text-gray-900 group-hover/link:text-blue-600 dark:text-gray-100 dark:group-hover/link:text-blue-400">
                            {{ getDomainFromUrl(monitor.url) }}
                        </h3>
                    </Link>
                    <div class="mt-1 flex items-center gap-2">
                        <span
                            :class="[
                                'h-2 w-2 rounded-full animate-pulse',
                                monitor.uptime_status === 'up' ? 'bg-green-500' : monitor.uptime_status === 'down' ? 'bg-red-500' : 'bg-yellow-500'
                            ]"
                        ></span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                            {{ monitor.uptime_status }}
                        </span>
                    </div>
                </div>

                <div class="text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        <span :class="['text-lg font-black tabular-nums tracking-tighter', getResponseTimeColor(monitor.latest_history?.response_time)]">
                            {{ monitor.latest_history?.response_time ?? '---' }}<span class="text-[10px] ml-0.5 opacity-50">ms</span>
                        </span>
                        <Icon v-if="getTrendIcon(monitor)" :name="getTrendIcon(monitor)!.name" :class="getTrendIcon(monitor)!.color" size="14" />
                    </div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">
                        AVG: {{ Math.round(monitor.statistics?.avg_response_time_24h ?? 0) }}ms
                    </div>
                </div>
            </div>

            <!-- Uptime Bar -->
            <div class="mt-4">
                <div class="mb-1.5 flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
                    <span class="text-gray-400">30D Uptime</span>
                    <span :class="monitor.statistics?.uptime_30d && monitor.statistics.uptime_30d >= 99 ? 'text-green-500' : 'text-yellow-500'">
                        {{ monitor.statistics?.uptime_30d ? monitor.statistics.uptime_30d.toFixed(2) : '---' }}%
                    </span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    <div
                        class="h-full bg-green-500 transition-all duration-500"
                        :style="{ width: `${monitor.statistics?.uptime_30d ?? 0}%`, filter: (monitor.statistics?.uptime_30d ?? 0) < 99 ? 'hue-rotate(-45deg)' : '' }"
                    ></div>
                </div>
            </div>

            <!-- Sparkline (Using simple colored bars for now) -->
            <div class="mt-4 flex h-8 items-end gap-0.5 overflow-hidden">
                <template v-if="monitor.statistics?.recent_history_100m">
                    <div
                        v-for="(point, idx) in monitor.statistics.recent_history_100m.slice(-40)"
                        :key="idx"
                        :class="[
                            'flex-1 rounded-t-[1px] transition-all hover:opacity-100',
                            point.status === 'up' ? 'bg-green-500/30' : 'bg-red-500',
                            idx === 39 ? 'opacity-100' : 'opacity-50'
                        ]"
                        :style="{ height: point.status === 'up' ? `${Math.min(100, (point.rt / 1000) * 100)}%` : '100%' }"
                        :title="`${point.rt}ms at ${point.time}`"
                    ></div>
                </template>
                <div v-else class="flex w-full items-center justify-center text-[10px] font-bold text-gray-300 uppercase tracking-widest">
                    No recent data
                </div>
            </div>
        </div>
    </div>
</template>

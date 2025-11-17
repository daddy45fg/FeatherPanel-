<template>
    <div class="status-container">
        <header class="status-header">
            <h1 class="status-title">{{ config.title }}</h1>
            <p class="status-subtitle">{{ config.company_name }} System Status</p>
        </header>

        <main class="status-main">
            <div id="overall-status" class="overall-status">
                <div class="status-indicator" :class="getStatusClass()">
                    <div class="status-dot"></div>
                </div>
                <h2 id="status-message">{{ getStatusMessage() }}</h2>
                <p id="status-details">{{ getStatusDetails() }}</p>
            </div>

            <div v-if="summary" id="nodes-section" class="nodes-section">
                <h3>Node Status</h3>
                <div id="nodes-grid" class="nodes-grid">
                    <div
                        v-for="node in nodes"
                        :key="node.name"
                        class="node-card"
                    >
                        <div class="node-header">
                            <div
                                :class="[
                                    'node-status',
                                    node.status === 'healthy' ? 'healthy' : 'unhealthy'
                                ]"
                            ></div>
                            <div class="node-name">{{ node.name }}</div>
                        </div>

                        <div v-if="config.show_locations && node.location" class="node-location">
                            {{ node.location }}
                        </div>

                        <div v-if="config.show_resource_usage" class="node-metrics">
                            <div class="metric">
                                <div class="metric-value">
                                    {{ formatMetric(node.cpu_percent) }}
                                </div>
                                <div class="metric-label">CPU</div>
                            </div>
                            <div class="metric">
                                <div class="metric-value">
                                    {{ formatMetric(node.memory_percent) }}
                                </div>
                                <div class="metric-label">Memory</div>
                            </div>
                            <div class="metric">
                                <div class="metric-value">
                                    {{ formatMetric(node.disk_percent) }}
                                </div>
                                <div class="metric-label">Disk</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="status-footer">
            <div class="footer-info">
                <p id="last-updated">Last updated: {{ lastUpdated }}</p>
                <p v-if="config.support_email" class="support-email">
                    Contact support: <a :href="`mailto:${config.support_email}`">{{ config.support_email }}</a>
                </p>
            </div>
        </footer>

        <!-- Refresh indicator -->
        <div v-if="loading" class="refresh-indicator">
            <div class="refresh-spinner"></div>
        </div>
    </div>
</template>

<script setup lang="ts">
// MIT License
//
// Copyright (c) 2025 MythicalSystems
// Copyright (c) 2025 Cassian Gherman (NaysKutzu)
// Copyright (c) 2018 - 2021 Dane Everitt <dane@daneeveritt.com> and Contributors
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.

import { ref, onMounted, onUnmounted, computed } from 'vue';

interface StatusConfig {
    title: string;
    company_name: string;
    support_email: string | null;
    auto_refresh_enabled: boolean;
    auto_refresh_interval: number;
    show_node_names: boolean;
    show_resource_usage: boolean;
    show_locations: boolean;
}

interface StatusSummary {
    overall_status: 'operational' | 'degraded' | 'down';
    healthy_nodes: number;
    total_nodes: number;
    avg_cpu_percent?: number;
    avg_memory_percent?: number;
}

interface StatusNode {
    name: string;
    location?: string | null;
    status: 'healthy' | 'unhealthy';
    cpu_percent?: number | null;
    memory_percent?: number | null;
    disk_percent?: number | null;
}

// Props
interface Props {
    config: StatusConfig;
}

const props = defineProps<Props>();

// State
const loading = ref(false);
const summary = ref<StatusSummary | null>(null);
const nodes = ref<StatusNode[]>([]);
const lastUpdated = ref<string>('');
let autoRefreshInterval: number | null = null;

// Computed
const getStatusClass = () => {
    if (!summary.value) return 'loading';

    switch (summary.value.overall_status) {
        case 'operational':
            return 'status-operational';
        case 'degraded':
            return 'status-degraded';
        case 'down':
            return 'status-down';
        default:
            return 'status-unknown';
    }
};

const getStatusMessage = () => {
    if (!summary.value) return 'Loading...';

    switch (summary.value.overall_status) {
        case 'operational':
            return 'All Systems Operational';
        case 'degraded':
            return 'Some Issues Detected';
        case 'down':
            return 'Service Unavailable';
        default:
            return 'Status Unknown';
    }
};

const getStatusDetails = () => {
    if (!summary.value) return 'Checking system status...';

    return `${summary.value.healthy_nodes} of ${summary.value.total_nodes} nodes are online`;
};

const formatMetric = (value: number | null | undefined): string => {
    if (value === null || value === undefined) return 'N/A';
    return value.toFixed(1) + '%';
};

const formatLastUpdated = (): string => {
    return new Date().toLocaleString();
};

// Methods
const updateStatus = async () => {
    if (loading.value) return;

    loading.value = true;

    try {
        const [summaryResponse, nodesResponse] = await Promise.all([
            fetch('/api/status/summary'),
            fetch('/api/status/nodes')
        ]);

        if (summaryResponse.ok) {
            const summaryData = await summaryResponse.json();
            summary.value = summaryData.data.summary;
        }

        if (nodesResponse.ok) {
            const nodesData = await nodesResponse.json();
            nodes.value = nodesData.data.nodes;
        }

        lastUpdated.value = formatLastUpdated();

    } catch (error) {
        console.error('Error updating status:', error);
        // Don't change UI on error - keep last known good state
    } finally {
        loading.value = false;
    }
};

const setupAutoRefresh = () => {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }

    if (props.config.auto_refresh_enabled && props.config.auto_refresh_interval > 0) {
        autoRefreshInterval = setInterval(
            updateStatus,
            props.config.auto_refresh_interval * 1000
        );
    }
};

// Lifecycle
onMounted(() => {
    updateStatus();
    setupAutoRefresh();
});

onUnmounted(() => {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
});

// Watch for config changes to update auto-refresh
watch(() => props.config.auto_refresh_enabled, () => {
    setupAutoRefresh();
});

watch(() => props.config.auto_refresh_interval, () => {
    setupAutoRefresh();
});
</script>

<style scoped>
.status-container {
    min-height: 100vh;
    background-color: #f8fafc;
    color: #334155;
    line-height: 1.6;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.status-header {
    text-align: center;
    margin-bottom: 3rem;
}

.status-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.status-subtitle {
    font-size: 1.25rem;
    color: #64748b;
}

.status-main {
    flex: 1;
}

.overall-status {
    text-align: center;
    margin-bottom: 3rem;
}

.status-indicator {
    margin-bottom: 2rem;
}

.status-dot {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto;
    transition: all 0.3s ease;
}

.status-operational .status-dot {
    background-color: #10b981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
}

.status-degraded .status-dot {
    background-color: #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
}

.status-down .status-dot {
    background-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
}

.status-unknown .status-dot {
    background-color: #6b7280;
    box-shadow: 0 0 0 4px rgba(107, 114, 128, 0.1);
}

#status-message {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #1e293b;
}

.status-operational #status-message {
    color: #10b981;
}

.status-degraded #status-message {
    color: #f59e0b;
}

.status-down #status-message {
    color: #ef4444;
}

#status-details {
    font-size: 1.125rem;
    color: #64748b;
}

.nodes-section {
    margin-top: 3rem;
}

.nodes-section h3 {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    color: #1e293b;
}

.nodes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.node-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.node-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.node-status {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 0.75rem;
}

.node-status.healthy {
    background-color: #10b981;
}

.node-status.unhealthy {
    background-color: #ef4444;
}

.node-name {
    font-weight: 600;
    color: #1e293b;
}

.node-location {
    font-size: 0.875rem;
    color: #64748b;
    margin-bottom: 1rem;
}

.node-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 1rem;
}

.metric {
    text-align: center;
}

.metric-value {
    font-weight: 600;
    color: #1e293b;
}

.metric-label {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
}

.status-footer {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid #e2e8f0;
    text-align: center;
}

.footer-info p {
    color: #64748b;
    margin-bottom: 0.5rem;
}

.support-email a {
    color: #3b82f6;
    text-decoration: none;
}

.support-email a:hover {
    text-decoration: underline;
}

.refresh-indicator {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 50;
}

.refresh-spinner {
    width: 24px;
    height: 24px;
    border: 2px solid #e2e8f0;
    border-top: 2px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .status-container {
        padding: 1rem;
    }

    .status-title {
        font-size: 2rem;
    }

    #status-message {
        font-size: 1.5rem;
    }

    .nodes-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<template>
    <div class="min-h-screen bg-background">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="text-center">
                <div
                    class="animate-spin rounded-full h-8 w-8 border-2 border-primary border-t-transparent mx-auto mb-4"
                ></div>
                <h3 class="text-lg font-semibold mb-2">Loading Status Page</h3>
                <p class="text-muted-foreground">Please wait while we load status information...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex flex-col items-center justify-center py-12 text-center">
            <div class="text-red-500 mb-4">
                <AlertCircle class="h-12 w-12 mx-auto" />
            </div>
            <h3 class="text-lg font-medium text-muted-foreground mb-2">Status Page Unavailable</h3>
            <p class="text-sm text-muted-foreground max-w-sm">{{ error }}</p>
            <Button class="mt-4" @click="loadStatusPage">Try Again</Button>
        </div>

        <!-- Status Dashboard -->
        <StatusDashboard
            v-else-if="config && !loading"
            :config="config"
        />

        <!-- Not Available Message -->
        <div v-else class="flex flex-col items-center justify-center py-12 text-center">
            <div class="text-gray-500 mb-4">
                <MonitorOff class="h-12 w-12 mx-auto" />
            </div>
            <h3 class="text-lg font-medium text-muted-foreground mb-2">Status Page Not Available</h3>
            <p class="text-sm text-muted-foreground max-w-sm">
                The status page is either not configured or has been disabled by the administrator.
            </p>
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

import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import StatusDashboard from '@/components/StatusDashboard.vue';
import {
    AlertCircle,
    MonitorOff,
} from 'lucide-vue-next';
import axios from 'axios';

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

// State
const loading = ref(true);
const error = ref<string | null>(null);
const config = ref<StatusConfig | null>(null);

// Methods
const loadStatusPage = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get('/api/status/config');

        if (response.data.success) {
            config.value = response.data.data;
        } else {
            error.value = response.data.message || 'Failed to load status page configuration';
        }
    } catch (err) {
        console.error('Error loading status page:', err);

        // Check if this is a network error (404 might mean status page is not active)
        if (err.response?.status === 403 || err.response?.status === 503) {
            error.value = 'This status page is currently disabled or not configured.';
        } else {
            error.value = 'Failed to load status page. Please try again later.';
        }
    } finally {
        loading.value = false;
    }
};

// Lifecycle
onMounted(() => {
    // No authentication required for public status page
    loadStatusPage();
});
</script>

<style scoped>
/* Additional styles if needed beyond what StatusDashboard provides */
.min-h-screen {
    min-height: 100vh;
}
</style>
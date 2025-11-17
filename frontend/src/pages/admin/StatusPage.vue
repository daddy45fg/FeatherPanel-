<template>
    <DashboardLayout :breadcrumbs="[{ text: 'Status Page', isCurrent: true, href: '/admin/status-page' }]">
        <div class="min-h-screen bg-background">
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="text-center">
                    <div
                        class="animate-spin rounded-full h-8 w-8 border-2 border-primary border-t-transparent mx-auto mb-4"
                    ></div>
                    <h3 class="text-lg font-semibold mb-2">Loading Status Page Configuration</h3>
                    <p class="text-muted-foreground">Please wait while we fetch your configuration...</p>
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="flex flex-col items-center justify-center py-12 text-center">
                <div class="text-red-500 mb-4">
                    <AlertCircle class="h-12 w-12 mx-auto" />
                </div>
                <h3 class="text-lg font-medium text-muted-foreground mb-2">Failed to load configuration</h3>
                <p class="text-sm text-muted-foreground max-w-sm">{{ error }}</p>
                <Button class="mt-4" @click="fetchConfiguration">Try Again</Button>
            </div>

            <!-- Main Content -->
            <div v-else class="p-4 sm:p-6 space-y-6">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-foreground mb-1">Status Page Configuration</h1>
                        <p class="text-sm sm:text-base text-muted-foreground">
                            Manage your public status page settings and domain configuration
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" @click="previewStatusPage" :disabled="!config.domain">
                            <Eye class="h-4 w-4 mr-2" />
                            Preview
                        </Button>
                        <Button @click="saveConfiguration" :disabled="saving">
                            <Save v-if="!saving" class="h-4 w-4 mr-2" />
                            <div
                                v-else
                                class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"
                            ></div>
                            {{ saving ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </div>

                <!-- Status Display Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Monitor class="h-5 w-5" />
                            Current Status
                        </CardTitle>
                        <CardDescription>
                            Overview of your status page configuration and domain status
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="p-4 border rounded-lg">
                                <div class="text-sm font-medium text-muted-foreground mb-1">Domain</div>
                                <div class="font-semibold">
                                    {{ config.domain || 'Not configured' }}
                                </div>
                            </div>
                            <div class="p-4 border rounded-lg">
                                <div class="text-sm font-medium text-muted-foreground mb-1">SSL Status</div>
                                <div class="flex items-center gap-2">
                                    <div
                                        :class="[
                                            'w-3 h-3 rounded-full',
                                            getSslStatusColor(config.ssl_status)
                                        ]"
                                    ></div>
                                    <span class="font-semibold capitalize">{{ config.ssl_status || 'pending' }}</span>
                                </div>
                            </div>
                            <div class="p-4 border rounded-lg">
                                <div class="text-sm font-medium text-muted-foreground mb-1">Page Status</div>
                                <div class="flex items-center gap-2">
                                    <div
                                        :class="[
                                            'w-3 h-3 rounded-full',
                                            config.is_active ? 'bg-green-500' : 'bg-gray-400'
                                        ]"
                                    ></div>
                                    <span class="font-semibold">{{ config.is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="config.domain" class="flex items-center gap-2 pt-2">
                            <Button variant="outline" size="sm" @click="testSslStatus">
                                <RefreshCw class="h-4 w-4 mr-2" />
                                Test SSL
                            </Button>
                            <Button variant="outline" size="sm" @click="copyStatusUrl">
                                <Copy class="h-4 w-4 mr-2" />
                                Copy URL
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Domain Configuration Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Globe class="h-5 w-5" />
                            Domain Configuration
                        </CardTitle>
                        <CardDescription>
                            Configure a custom domain for your status page with automatic SSL through Cloudflare
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="domain">Custom Domain</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="domain"
                                    v-model="domainForm.domain"
                                    placeholder="status.example.com"
                                    :disabled="domainForm.loading"
                                    class="flex-1"
                                />
                                <Button
                                    @click="setupDomain"
                                    :disabled="!domainForm.domain || domainForm.loading"
                                >
                                    <div
                                        v-if="domainForm.loading"
                                        class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"
                                    ></div>
                                    <Plus v-else class="h-4 w-4 mr-2" />
                                    Setup Domain
                                </Button>
                                <Button
                                    v-if="config.domain"
                                    variant="destructive"
                                    @click="removeDomain"
                                    :disabled="domainForm.loading"
                                >
                                    <Trash2 class="h-4 w-4 mr-2" />
                                    Remove
                                </Button>
                            </div>
                        </div>

                        <div v-if="domainForm.cloudflare_account_id" class="space-y-2">
                            <Label for="cloudflare_account_id">Cloudflare Account ID</Label>
                            <Input
                                id="cloudflare_account_id"
                                v-model="domainForm.cloudflare_account_id"
                                placeholder="Cloudflare Account ID"
                                :disabled="domainForm.loading"
                            />
                        </div>

                        <div v-if="domainForm.error" class="p-3 border border-red-200 bg-red-50 rounded-lg">
                            <div class="flex items-center gap-2 text-red-700">
                                <AlertCircle class="h-4 w-4" />
                                <span class="text-sm">{{ domainForm.error }}</span>
                            </div>
                        </div>

                        <div v-if="domainForm.success" class="p-3 border border-green-200 bg-green-50 rounded-lg">
                            <div class="flex items-center gap-2 text-green-700">
                                <CheckCircle class="h-4 w-4" />
                                <span class="text-sm">{{ domainForm.success }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Page Customization Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Palette class="h-5 w-5" />
                            Page Customization
                        </CardTitle>
                        <CardDescription>
                            Customize the appearance and information displayed on your status page
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="title">Status Page Title</Label>
                                <Input
                                    id="title"
                                    v-model="config.title"
                                    placeholder="FeatherPanel Status"
                                />
                                <p class="text-xs text-muted-foreground">
                                    The main title displayed on the status page
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="company_name">Company Name</Label>
                                <Input
                                    id="company_name"
                                    v-model="config.company_name"
                                    placeholder="FeatherPanel"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Your company or organization name
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="support_email">Support Email</Label>
                            <Input
                                id="support_email"
                                v-model="config.support_email"
                                type="email"
                                placeholder="support@example.com"
                            />
                            <p class="text-xs text-muted-foreground">
                                Contact email displayed on the status page (optional)
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Display Options Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Settings class="h-5 w-5" />
                            Display Options
                        </CardTitle>
                        <CardDescription>
                            Control what information is displayed on the public status page
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center justify-between">
                                <div class="space-y-1">
                                    <Label for="show_node_names">Show Node Names</Label>
                                    <p class="text-xs text-muted-foreground">
                                        Display individual node names on the status page
                                    </p>
                                </div>
                                <Switch
                                    id="show_node_names"
                                    v-model:checked="config.show_node_names"
                                />
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="space-y-1">
                                    <Label for="show_resource_usage">Show Resource Usage</Label>
                                    <p class="text-xs text-muted-foreground">
                                        Display CPU, memory, and disk usage metrics
                                    </p>
                                </div>
                                <Switch
                                    id="show_resource_usage"
                                    v-model:checked="config.show_resource_usage"
                                />
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="space-y-1">
                                    <Label for="show_locations">Show Locations</Label>
                                    <p class="text-xs text-muted-foreground">
                                        Display node location information
                                    </p>
                                </div>
                                <Switch
                                    id="show_locations"
                                    v-model:checked="config.show_locations"
                                />
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="space-y-1">
                                    <Label for="auto_refresh_enabled">Auto Refresh</Label>
                                    <p class="text-xs text-muted-foreground">
                                        Enable automatic status updates on the page
                                    </p>
                                </div>
                                <Switch
                                    id="auto_refresh_enabled"
                                    v-model:checked="config.auto_refresh_enabled"
                                />
                            </div>
                        </div>

                        <div v-if="config.auto_refresh_enabled" class="space-y-2">
                            <Label for="auto_refresh_interval">Refresh Interval (seconds)</Label>
                            <Input
                                id="auto_refresh_interval"
                                v-model.number="config.auto_refresh_interval"
                                type="number"
                                min="5"
                                max="300"
                                placeholder="30"
                            />
                            <p class="text-xs text-muted-foreground">
                                How often to refresh status data (5-300 seconds)
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Page Activation Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Power class="h-5 w-5" />
                            Page Activation
                        </CardTitle>
                        <CardDescription>
                            Enable or disable the public status page
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center justify-between p-4 border rounded-lg">
                            <div class="space-y-1">
                                <Label for="is_active" class="text-base font-medium">Enable Status Page</Label>
                                <p class="text-sm text-muted-foreground">
                                    Make your status page publicly accessible
                                    {{ config.domain ? ` at https://${config.domain}` : '' }}
                                </p>
                            </div>
                            <Switch
                                id="is_active"
                                v-model:checked="config.is_active"
                                size="lg"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </DashboardLayout>
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
import { useSessionStore } from '@/stores/session';
import { useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';
import {
    Eye,
    Save,
    Monitor,
    Globe,
    Palette,
    Settings,
    Power,
    RefreshCw,
    Copy,
    Plus,
    Trash2,
    AlertCircle,
    CheckCircle,
} from 'lucide-vue-next';

interface StatusPageConfig {
    uuid: string;
    domain: string;
    title: string;
    company_name: string;
    support_email: string | null;
    is_active: boolean;
    show_node_names: boolean;
    show_resource_usage: boolean;
    show_locations: boolean;
    auto_refresh_enabled: boolean;
    auto_refresh_interval: number;
    ssl_status: 'pending' | 'active' | 'failed';
    cloudflare_zone_id: string | null;
    cloudflare_record_id: string | null;
}

const toast = useToast();
const sessionStore = useSessionStore();
const router = useRouter();

// State
const loading = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);
const config = ref<StatusPageConfig>({
    uuid: '',
    domain: '',
    title: 'FeatherPanel Status',
    company_name: 'FeatherPanel',
    support_email: null,
    is_active: false,
    show_node_names: true,
    show_resource_usage: true,
    show_locations: false,
    auto_refresh_enabled: true,
    auto_refresh_interval: 30,
    ssl_status: 'pending',
    cloudflare_zone_id: null,
    cloudflare_record_id: null,
});

const domainForm = ref({
    domain: '',
    cloudflare_account_id: '',
    loading: false,
    error: string | null,
    success: string | null,
});

// Methods
const fetchConfiguration = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get('/api/admin/status-page');

        if (response.data.success) {
            config.value = response.data.data;
            domainForm.value.domain = config.value.domain || '';
        } else {
            error.value = response.data.message || 'Failed to load configuration';
        }
    } catch (err) {
        console.error('Error fetching status page configuration:', err);
        error.value = 'Failed to load configuration. Please try again.';
    } finally {
        loading.value = false;
    }
};

const saveConfiguration = async () => {
    saving.value = true;

    try {
        const response = await axios.put('/api/admin/status-page', {
            title: config.value.title,
            company_name: config.value.company_name,
            support_email: config.value.support_email,
            is_active: config.value.is_active,
            show_node_names: config.value.show_node_names,
            show_resource_usage: config.value.show_resource_usage,
            show_locations: config.value.show_locations,
            auto_refresh_enabled: config.value.auto_refresh_enabled,
            auto_refresh_interval: config.value.auto_refresh_interval,
        });

        if (response.data.success) {
            config.value = response.data.data;
            toast.success('Configuration saved successfully');
        } else {
            toast.error(response.data.message || 'Failed to save configuration');
        }
    } catch (err) {
        console.error('Error saving status page configuration:', err);
        toast.error('Failed to save configuration. Please try again.');
    } finally {
        saving.value = false;
    }
};

const setupDomain = async () => {
    if (!domainForm.value.domain) {
        toast.error('Domain is required');
        return;
    }

    domainForm.value.loading = true;
    domainForm.value.error = null;
    domainForm.value.success = null;

    try {
        const response = await axios.post('/api/admin/status-page/domain', {
            domain: domainForm.value.domain,
            cloudflare_account_id: domainForm.value.cloudflare_account_id,
        });

        if (response.data.success) {
            config.value.domain = response.data.data.domain;
            config.value.ssl_status = response.data.data.ssl_status;
            config.value.cloudflare_zone_id = response.data.data.cloudflare_zone_id;
            config.value.cloudflare_record_id = response.data.data.cloudflare_record_id;

            domainForm.value.success = response.data.data.message;
            toast.success('Domain setup completed successfully');
        } else {
            domainForm.value.error = response.data.message || 'Failed to setup domain';
        }
    } catch (err) {
        console.error('Error setting up domain:', err);
        domainForm.value.error = 'Failed to setup domain. Please check your Cloudflare configuration.';
    } finally {
        domainForm.value.loading = false;
    }
};

const removeDomain = async () => {
    if (!confirm('Are you sure you want to remove the domain configuration? This will make your status page inaccessible.')) {
        return;
    }

    domainForm.value.loading = true;

    try {
        const response = await axios.delete('/api/admin/status-page/domain');

        if (response.data.success) {
            config.value.domain = '';
            config.value.ssl_status = 'pending';
            config.value.cloudflare_zone_id = null;
            config.value.cloudflare_record_id = null;
            config.value.is_active = false;
            domainForm.value.domain = '';

            toast.success('Domain configuration removed successfully');
        } else {
            toast.error(response.data.message || 'Failed to remove domain');
        }
    } catch (err) {
        console.error('Error removing domain:', err);
        toast.error('Failed to remove domain. Please try again.');
    } finally {
        domainForm.value.loading = false;
    }
};

const testSslStatus = async () => {
    try {
        toast.info('Testing SSL status...');

        // Simulate SSL test - in real implementation this would check the SSL status
        await new Promise(resolve => setTimeout(resolve, 2000));

        if (config.value.ssl_status === 'active') {
            toast.success('SSL certificate is valid and active');
        } else {
            toast.warning('SSL certificate is still being processed by Cloudflare');
        }
    } catch (err) {
        console.error('Error testing SSL:', err);
        toast.error('Failed to test SSL status');
    }
};

const copyStatusUrl = async () => {
    if (!config.value.domain) {
        toast.error('Domain not configured');
        return;
    }

    const url = `https://${config.value.domain}`;

    try {
        await navigator.clipboard.writeText(url);
        toast.success('Status page URL copied to clipboard');
    } catch {
        toast.error('Failed to copy URL');
    }
};

const previewStatusPage = () => {
    if (!config.value.domain) {
        toast.error('Domain not configured');
        return;
    }

    window.open(`https://${config.value.domain}`, '_blank');
};

const getSslStatusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-500';
        case 'failed':
            return 'bg-red-500';
        default:
            return 'bg-yellow-500';
    }
};

// Lifecycle
onMounted(async () => {
    const ok = await sessionStore.checkSessionOrRedirect(router);
    if (!ok) return;

    await fetchConfiguration();
});
</script>
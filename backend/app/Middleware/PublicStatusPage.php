<?php

/*
 * This file is part of FeatherPanel.
 *
 * MIT License
 *
 * Copyright (c) 2025 MythicalSystems
 * Copyright (c) 2025 Cassian Gherman (NaysKutzu)
 * Copyright (c) 2018 - 2021 Dane Everitt <dane@daneeveritt.com> and Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Middleware;

use App\App;
use App\Helpers\ApiResponse;
use App\Models\StatusPage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicStatusPage implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $host = $request->getHost();
        $uri = $request->getRequestUri();

        // Skip API routes and admin routes
        if (strpos($uri, '/api/') === 0 || strpos($uri, '/admin') === 0) {
            return $next($request);
        }

        // Check if this domain matches a status page configuration
        $statusConfig = StatusPage::getConfigurationByDomain($host);

        if (!$statusConfig) {
            return $next($request);
        }

        // Check if status page is active
        if (!$statusConfig['is_active']) {
            return new Response('Status page is not active', 503);
        }

        // Handle SSL redirection (force HTTPS for status pages)
        if ($request->getScheme() !== 'https' && $statusConfig['ssl_status'] === 'active') {
            $httpsUrl = 'https://' . $host . $uri;
            return new Response('', 301, ['Location' => $httpsUrl]);
        }

        // Serve the status page
        return $this->serveStatusPage($request, $statusConfig);
    }

    /**
     * Serve the status page HTML with the configuration.
     */
    private function serveStatusPage(Request $request, array $statusConfig): Response
    {
        $host = $request->getHost();

        // Generate status page HTML
        $html = $this->generateStatusPageHtml($statusConfig, $host);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Generate the HTML for the status page.
     */
    private function generateStatusPageHtml(array $config, string $host): string
    {
        $title = htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8');
        $companyName = htmlspecialchars($config['company_name'], ENT_QUOTES, 'UTF-8');
        $autoRefresh = $config['auto_refresh_enabled'] ? (int) $config['auto_refresh_interval'] : 0;

        // Status page styling
        $styles = $this->getStatusPageStyles();

        // JavaScript for auto-refresh and status updates
        $script = $this->getStatusPageScript($autoRefresh);

        return "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title}</title>
    <meta name='description' content='{$companyName} system status page'>
    <style>{$styles}</style>
</head>
<body>
    <div class='status-container'>
        <header class='status-header'>
            <h1 class='status-title'>{$title}</h1>
            <p class='status-subtitle'>{$companyName} System Status</p>
        </header>

        <main class='status-main'>
            <div id='overall-status' class='overall-status'>
                <div class='status-indicator' id='status-indicator'>
                    <div class='status-dot'></div>
                </div>
                <h2 id='status-message'>Loading...</h2>
                <p id='status-details'>Checking system status...</p>
            </div>

            <div id='nodes-section' class='nodes-section' style='display: none;'>
                <h3>Node Status</h3>
                <div id='nodes-grid' class='nodes-grid'>
                    <!-- Nodes will be populated by JavaScript -->
                </div>
            </div>
        </main>

        <footer class='status-footer'>
            <div class='footer-info'>
                <p id='last-updated'>Last updated: Loading...</p>
                " . ($config['support_email'] ? "<p>Contact support: <a href='mailto:" . htmlspecialchars($config['support_email'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($config['support_email'], ENT_QUOTES, 'UTF-8') . "</a></p>" : "") . "
            </div>
        </footer>
    </div>

    <script>{$script}</script>
</body>
</html>";
    }

    /**
     * Get the CSS styles for the status page.
     */
    private function getStatusPageStyles(): string
    {
        return "
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background-color: #f8fafc;
    color: #334155;
    line-height: 1.6;
}

.status-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
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

.footer-info a {
    color: #3b82f6;
    text-decoration: none;
}

.footer-info a:hover {
    text-decoration: underline;
}

.loading {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
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
        ";
    }

    /**
     * Get the JavaScript for the status page functionality.
     */
    private function getStatusPageScript(int $autoRefreshInterval): string
    {
        $autoRefreshScript = '';
        if ($autoRefreshInterval > 0) {
            $autoRefreshScript = "
                // Auto-refresh functionality
                setInterval(updateStatus, {$autoRefreshInterval}000);
            ";
        }

        return "
        let autoRefreshInterval = {$autoRefreshInterval};

        // Update status on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateStatus();
        });

        async function updateStatus() {
            try {
                const [summaryResponse, configResponse, nodesResponse] = await Promise.all([
                    fetch('/api/status/summary'),
                    fetch('/api/status/config'),
                    fetch('/api/status/nodes')
                ]);

                if (!summaryResponse.ok || !configResponse.ok || !nodesResponse.ok) {
                    showError('Failed to load status data');
                    return;
                }

                const summary = await summaryResponse.json();
                const config = await configResponse.json();
                const nodes = await nodesResponse.json();

                updateOverallStatus(summary.data.summary);
                updateNodes(nodes.data.nodes, config.data.config);
                updateLastUpdated();

            } catch (error) {
                console.error('Error updating status:', error);
                showError('Failed to load status data');
            }
        }

        function updateOverallStatus(summary) {
            const statusElement = document.getElementById('overall-status');
            const messageElement = document.getElementById('status-message');
            const detailsElement = document.getElementById('status-details');

            // Remove all status classes
            statusElement.className = 'overall-status';

            if (summary.overall_status === 'operational') {
                statusElement.classList.add('status-operational');
                messageElement.textContent = 'All Systems Operational';
                detailsElement.textContent = summary.healthy_nodes + ' of ' + summary.total_nodes + ' nodes are online';
            } else if (summary.overall_status === 'degraded') {
                statusElement.classList.add('status-degraded');
                messageElement.textContent = 'Some Issues Detected';
                detailsElement.textContent = summary.healthy_nodes + ' of ' + summary.total_nodes + ' nodes are online';
            } else {
                statusElement.classList.add('status-down');
                messageElement.textContent = 'Service Unavailable';
                detailsElement.textContent = 'Unable to connect to nodes';
            }
        }

        function updateNodes(nodes, config) {
            const nodesSection = document.getElementById('nodes-section');
            const nodesGrid = document.getElementById('nodes-grid');

            nodesGrid.innerHTML = '';

            nodes.forEach(node => {
                const nodeCard = createNodeCard(node, config);
                nodesGrid.appendChild(nodeCard);
            });

            nodesSection.style.display = 'block';
        }

        function createNodeCard(node, config) {
            const card = document.createElement('div');
            card.className = 'node-card';

            const statusClass = node.status === 'healthy' ? 'healthy' : 'unhealthy';
            const statusText = node.status === 'healthy' ? 'Healthy' : 'Unhealthy';

            let metricsHtml = '';
            if (config.show_resource_usage) {
                const cpu = node.cpu_percent !== null ? node.cpu_percent.toFixed(1) : 'N/A';
                const memory = node.memory_percent !== null ? node.memory_percent.toFixed(1) : 'N/A';
                const disk = node.disk_percent !== null ? node.disk_percent.toFixed(1) : 'N/A';

                metricsHtml = `
                    <div class='node-metrics'>
                        <div class='metric'>
                            <div class='metric-value'>${cpu}%</div>
                            <div class='metric-label'>CPU</div>
                        </div>
                        <div class='metric'>
                            <div class='metric-value'>${memory}%</div>
                            <div class='metric-label'>Memory</div>
                        </div>
                        <div class='metric'>
                            <div class='metric-value'>${disk}%</div>
                            <div class='metric-label'>Disk</div>
                        </div>
                    </div>
                `;
            }

            let locationHtml = '';
            if (config.show_locations && node.location) {
                locationHtml = `<div class='node-location'>${node.location}</div>`;
            }

            card.innerHTML = `
                <div class='node-header'>
                    <div class='node-status ${statusClass}'></div>
                    <div class='node-name'>${node.name}</div>
                </div>
                ${locationHtml}
                ${metricsHtml}
            `;

            return card;
        }

        function updateLastUpdated() {
            const now = new Date();
            const formatted = now.toLocaleString();
            document.getElementById('last-updated').textContent = 'Last updated: ' + formatted;
        }

        function showError(message) {
            const messageElement = document.getElementById('status-message');
            const detailsElement = document.getElementById('status-details');

            messageElement.textContent = 'Status Unavailable';
            detailsElement.textContent = message;

            document.getElementById('overall-status').className = 'overall-status status-down';
        }

        {$autoRefreshScript}
        ";
    }
}
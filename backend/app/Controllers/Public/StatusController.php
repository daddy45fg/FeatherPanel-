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

namespace App\Controllers\Public;

use App\Chat\Node;
use App\Helpers\ApiResponse;
use App\Models\StatusPage;
use App\Services\Wings\Wings;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StatusController
{
    #[OA\Get(
        path: '/api/status/summary',
        summary: 'Get public status summary',
        description: 'Retrieve a summary of system status including overall health and basic statistics. No authentication required.',
        tags: ['Public - Status'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status summary retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'summary', type: 'object', properties: [
                            new OA\Property(property: 'overall_status', type: 'string', enum: ['operational', 'degraded', 'down']),
                            new OA\Property(property: 'healthy_nodes', type: 'integer'),
                            new OA\Property(property: 'total_nodes', type: 'integer'),
                            new OA\Property(property: 'avg_cpu_percent', type: 'number'),
                            new OA\Property(property: 'avg_memory_percent', type: 'number'),
                        ])
                    ]
                )
            ),
            new OA\Response(response: 503, description: 'Service temporarily unavailable'),
        ]
    )]
    public function getSummary(Request $request): Response
    {
        try {
            // Get status page configuration for customization
            $statusPageConfig = StatusPage::getActiveConfiguration();

            if (!$statusPageConfig || !$statusPageConfig['is_active']) {
                return ApiResponse::error('Status page is not active', 503);
            }

            $allNodes = Node::getAllNodes();

            if (empty($allNodes)) {
                return ApiResponse::success([
                    'summary' => [
                        'overall_status' => 'operational',
                        'healthy_nodes' => 0,
                        'total_nodes' => 0,
                        'avg_cpu_percent' => 0.0,
                        'avg_memory_percent' => 0.0,
                    ]
                ], 'Status summary retrieved successfully');
            }

            $healthyNodes = 0;
            $totalNodes = count($allNodes);
            $totalCpuPercent = 0.0;
            $totalMemoryPercent = 0.0;
            $healthyNodeCount = 0;

            foreach ($allNodes as $node) {
                try {
                    $wings = new Wings(
                        $node['fqdn'],
                        $node['daemonListen'],
                        $node['scheme'],
                        $node['daemon_token'],
                        5 // Short timeout for public status checks
                    );

                    $utilization = $wings->getSystem()->getSystemUtilization();

                    if (is_array($utilization) && !empty($utilization)) {
                        $healthyNodes++;
                        $healthyNodeCount++;

                        if (isset($utilization['cpu_percent'])) {
                            $totalCpuPercent += $utilization['cpu_percent'];
                        }

                        if (isset($utilization['memory_total']) && isset($utilization['memory_used']) && $utilization['memory_total'] > 0) {
                            $totalMemoryPercent += ($utilization['memory_used'] / $utilization['memory_total']) * 100;
                        }
                    }
                } catch (\Exception $e) {
                    // Node is unhealthy, continue to next
                    continue;
                }
            }

            // Calculate averages
            $avgCpuPercent = $healthyNodeCount > 0 ? round($totalCpuPercent / $healthyNodeCount, 2) : 0.0;
            $avgMemoryPercent = $healthyNodeCount > 0 ? round($totalMemoryPercent / $healthyNodeCount, 2) : 0.0;

            // Determine overall status
            if ($healthyNodes === 0) {
                $overallStatus = 'down';
            } elseif ($healthyNodes === $totalNodes) {
                $overallStatus = 'operational';
            } else {
                $overallStatus = 'degraded';
            }

            $summary = [
                'overall_status' => $overallStatus,
                'healthy_nodes' => $healthyNodes,
                'total_nodes' => $totalNodes,
                'avg_cpu_percent' => $avgCpuPercent,
                'avg_memory_percent' => $avgMemoryPercent,
            ];

            // Apply configuration filters
            if (!$statusPageConfig['show_resource_usage']) {
                unset($summary['avg_cpu_percent'], $summary['avg_memory_percent']);
            }

            return ApiResponse::success(['summary' => $summary], 'Status summary retrieved successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve status summary: ' . $e->getMessage(), 503);
        }
    }

    #[OA\Get(
        path: '/api/status/nodes',
        summary: 'Get public detailed node list',
        description: 'Retrieve detailed information about all nodes including status and resource usage. No authentication required.',
        tags: ['Public - Status'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Node list retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'nodes', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'location', type: 'string', nullable: true),
                                new OA\Property(property: 'status', type: 'string', enum: ['healthy', 'unhealthy']),
                                new OA\Property(property: 'cpu_percent', type: 'number', nullable: true),
                                new OA\Property(property: 'memory_percent', type: 'number', nullable: true),
                                new OA\Property(property: 'disk_percent', type: 'number', nullable: true),
                            ]
                        ))
                    ]
                )
            ),
            new OA\Response(response: 503, description: 'Service temporarily unavailable'),
        ]
    )]
    public function getNodes(Request $request): Response
    {
        try {
            // Get status page configuration for customization
            $statusPageConfig = StatusPage::getActiveConfiguration();

            if (!$statusPageConfig || !$statusPageConfig['is_active']) {
                return ApiResponse::error('Status page is not active', 503);
            }

            $allNodes = Node::getAllNodes();

            if (empty($allNodes)) {
                return ApiResponse::success(['nodes' => []], 'Node list retrieved successfully');
            }

            $nodesWithStatus = [];

            foreach ($allNodes as $node) {
                $nodeData = [
                    'name' => $node['name'],
                    'location' => null, // Will be populated if show_locations is enabled
                    'status' => 'unhealthy',
                    'cpu_percent' => null,
                    'memory_percent' => null,
                    'disk_percent' => null,
                ];

                // Only include node name if configured
                if (!$statusPageConfig['show_node_names']) {
                    $nodeData['name'] = 'Node ' . count($nodesWithStatus) + 1;
                }

                try {
                    $wings = new Wings(
                        $node['fqdn'],
                        $node['daemonListen'],
                        $node['scheme'],
                        $node['daemon_token'],
                        5 // Short timeout for public status checks
                    );

                    $utilization = $wings->getSystem()->getSystemUtilization();

                    if (is_array($utilization) && !empty($utilization)) {
                        $nodeData['status'] = 'healthy';

                        // Only include resource usage if configured
                        if ($statusPageConfig['show_resource_usage']) {
                            if (isset($utilization['cpu_percent'])) {
                                $nodeData['cpu_percent'] = round($utilization['cpu_percent'], 2);
                            }

                            if (isset($utilization['memory_total']) && isset($utilization['memory_used']) && $utilization['memory_total'] > 0) {
                                $nodeData['memory_percent'] = round(($utilization['memory_used'] / $utilization['memory_total']) * 100, 2);
                            }

                            if (isset($utilization['disk_total']) && isset($utilization['disk_used']) && $utilization['disk_total'] > 0) {
                                $nodeData['disk_percent'] = round(($utilization['disk_used'] / $utilization['disk_total']) * 100, 2);
                            }
                        }
                    }

                    // Include location if configured and location data is available
                    if ($statusPageConfig['show_locations'] && isset($node['location_id'])) {
                        // This would need to query the location from the database
                        // For now, we'll set a placeholder
                        $nodeData['location'] = 'Location ' . $node['location_id'];
                    }

                } catch (\Exception $e) {
                    // Node is unhealthy, continue with default values
                    $nodeData['error'] = 'Connection failed';
                }

                $nodesWithStatus[] = $nodeData;
            }

            return ApiResponse::success(['nodes' => $nodesWithStatus], 'Node list retrieved successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve node list: ' . $e->getMessage(), 503);
        }
    }

    #[OA\Get(
        path: '/api/status/config',
        summary: 'Get status page configuration',
        description: 'Retrieve status page configuration including title, company name, and display settings. No authentication required.',
        tags: ['Public - Status'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status page configuration retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'config', type: 'object', properties: [
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'company_name', type: 'string'),
                            new OA\Property(property: 'support_email', type: 'string', nullable: true),
                            new OA\Property(property: 'auto_refresh_enabled', type: 'boolean'),
                            new OA\Property(property: 'auto_refresh_interval', type: 'integer'),
                            new OA\Property(property: 'show_node_names', type: 'boolean'),
                            new OA\Property(property: 'show_resource_usage', type: 'boolean'),
                            new OA\Property(property: 'show_locations', type: 'boolean'),
                        ])
                    ]
                )
            ),
            new OA\Response(response: 503, description: 'Service temporarily unavailable'),
        ]
    )]
    public function getConfig(Request $request): Response
    {
        try {
            $statusPageConfig = StatusPage::getActiveConfiguration();

            if (!$statusPageConfig || !$statusPageConfig['is_active']) {
                return ApiResponse::error('Status page is not active', 503);
            }

            // Return only public-safe configuration
            $publicConfig = [
                'title' => $statusPageConfig['title'],
                'company_name' => $statusPageConfig['company_name'],
                'support_email' => $statusPageConfig['support_email'],
                'auto_refresh_enabled' => (bool) $statusPageConfig['auto_refresh_enabled'],
                'auto_refresh_interval' => (int) $statusPageConfig['auto_refresh_interval'],
                'show_node_names' => (bool) $statusPageConfig['show_node_names'],
                'show_resource_usage' => (bool) $statusPageConfig['show_resource_usage'],
                'show_locations' => (bool) $statusPageConfig['show_locations'],
            ];

            return ApiResponse::success(['config' => $publicConfig], 'Status page configuration retrieved successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve status page configuration: ' . $e->getMessage(), 503);
        }
    }
}
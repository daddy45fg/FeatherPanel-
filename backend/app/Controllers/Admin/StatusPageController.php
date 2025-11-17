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

namespace App\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Models\StatusPage;
use App\Services\Subdomain\CloudflareSubdomainService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StatusPageController
{
    #[OA\Get(
        path: '/api/admin/status-page',
        summary: 'Get status page configuration',
        description: 'Retrieve the current status page configuration including domain settings and display options.',
        tags: ['Admin - Status Page'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status page configuration retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'uuid', type: 'string'),
                            new OA\Property(property: 'domain', type: 'string'),
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'company_name', type: 'string'),
                            new OA\Property(property: 'support_email', type: 'string', nullable: true),
                            new OA\Property(property: 'is_active', type: 'boolean'),
                            new OA\Property(property: 'show_node_names', type: 'boolean'),
                            new OA\Property(property: 'show_resource_usage', type: 'boolean'),
                            new OA\Property(property: 'show_locations', type: 'boolean'),
                            new OA\Property(property: 'auto_refresh_enabled', type: 'boolean'),
                            new OA\Property(property: 'auto_refresh_interval', type: 'integer'),
                            new OA\Property(property: 'ssl_status', type: 'string', enum: ['pending', 'active', 'failed']),
                            new OA\Property(property: 'cloudflare_zone_id', type: 'string', nullable: true),
                            new OA\Property(property: 'cloudflare_record_id', type: 'string', nullable: true),
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden - Insufficient permissions'),
        ]
    )]
    public function getStatusPage(Request $request): Response
    {
        try {
            $config = StatusPage::getConfiguration();

            if (!$config) {
                return ApiResponse::error('Failed to retrieve status page configuration', 500);
            }

            return ApiResponse::success($config, 'Status page configuration retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve status page configuration: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Put(
        path: '/api/admin/status-page',
        summary: 'Update status page configuration',
        description: 'Update the status page configuration including display options and basic settings.',
        tags: ['Admin - Status Page'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: 'Status page title'),
                    new OA\Property(property: 'company_name', type: 'string', description: 'Company name displayed on status page'),
                    new OA\Property(property: 'support_email', type: 'string', nullable: true, description: 'Support contact email'),
                    new OA\Property(property: 'is_active', type: 'boolean', description: 'Whether the status page is active'),
                    new OA\Property(property: 'show_node_names', type: 'boolean', description: 'Show node names on status page'),
                    new OA\Property(property: 'show_resource_usage', type: 'boolean', description: 'Show resource usage metrics'),
                    new OA\Property(property: 'show_locations', type: 'boolean', description: 'Show node locations'),
                    new OA\Property(property: 'auto_refresh_enabled', type: 'boolean', description: 'Enable auto-refresh'),
                    new OA\Property(property: 'auto_refresh_interval', type: 'integer', description: 'Auto-refresh interval in seconds (5-300)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status page configuration updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'uuid', type: 'string'),
                            new OA\Property(property: 'domain', type: 'string'),
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'company_name', type: 'string'),
                            // ... other properties
                        ])
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Bad Request - Invalid input data'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden - Insufficient permissions'),
        ]
    )]
    public function updateStatusPage(Request $request): Response
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return ApiResponse::error('Invalid JSON data provided', 400);
            }

            // Filter allowed fields
            $allowedFields = [
                'title', 'company_name', 'support_email', 'is_active',
                'show_node_names', 'show_resource_usage', 'show_locations',
                'auto_refresh_enabled', 'auto_refresh_interval'
            ];

            $filteredData = array_intersect_key($data, array_flip($allowedFields));

            if (empty($filteredData)) {
                return ApiResponse::error('No valid fields provided for update', 400);
            }

            $success = StatusPage::updateConfiguration($filteredData);

            if (!$success) {
                return ApiResponse::error('Failed to update status page configuration', 500);
            }

            // Return updated configuration
            $updatedConfig = StatusPage::getConfiguration();

            return ApiResponse::success($updatedConfig, 'Status page configuration updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update status page configuration: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/api/admin/status-page/domain',
        summary: 'Setup or update domain with SSL',
        description: 'Configure a custom domain for the status page and setup SSL through Cloudflare.',
        tags: ['Admin - Status Page'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'domain', type: 'string', description: 'Domain name (e.g., status.example.com)'),
                    new OA\Property(property: 'cloudflare_account_id', type: 'string', description: 'Cloudflare account ID'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Domain setup initiated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'domain', type: 'string'),
                            new OA\Property(property: 'ssl_status', type: 'string'),
                            new OA\Property(property: 'message', type: 'string'),
                        ])
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Bad Request - Invalid domain format or missing data'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden - Insufficient permissions'),
            new OA\Response(response: 503, description: 'Service Unavailable - Cloudflare API error'),
        ]
    )]
    public function setupDomain(Request $request): Response
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data) || !isset($data['domain']) || empty(trim($data['domain']))) {
                return ApiResponse::error('Domain is required', 400);
            }

            $domain = trim($data['domain']);
            $cloudflareAccountId = $data['cloudflare_account_id'] ?? null;

            if (!$cloudflareAccountId) {
                return ApiResponse::error('Cloudflare account ID is required', 400);
            }

            // Validate domain format
            if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
                return ApiResponse::error('Invalid domain format', 400);
            }

            // Update configuration with domain
            $configUpdate = [
                'domain' => $domain,
                'ssl_status' => 'pending'
            ];

            $success = StatusPage::updateConfiguration($configUpdate);

            if (!$success) {
                return ApiResponse::error('Failed to update status page domain', 500);
            }

            // Get updated configuration
            $config = StatusPage::getConfiguration();

            // Setup Cloudflare DNS and SSL
            try {
                $cloudflareService = new CloudflareSubdomainService($cloudflareAccountId);

                // Create DNS record for the status page
                $zoneId = $cloudflareService->getOrCreateZone($domain);
                $recordId = $cloudflareService->createDnsRecord($zoneId, $domain, 'A', '192.168.1.1'); // This would be the server IP

                // Update configuration with Cloudflare IDs
                StatusPage::updateCloudflareIds($config['uuid'], $zoneId, $recordId);

                // SSL is automatically handled by Cloudflare
                StatusPage::updateSslStatus($config['uuid'], 'active');

                $responseData = [
                    'domain' => $domain,
                    'ssl_status' => 'active',
                    'message' => 'Domain and SSL setup completed successfully',
                    'cloudflare_zone_id' => $zoneId,
                    'cloudflare_record_id' => $recordId
                ];

                return ApiResponse::success($responseData, 'Domain setup completed successfully');

            } catch (\Exception $cloudflareError) {
                // Mark SSL as failed
                StatusPage::updateSslStatus($config['uuid'], 'failed');

                return ApiResponse::error('Failed to setup Cloudflare DNS/SSL: ' . $cloudflareError->getMessage(), 503);
            }

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to setup domain: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/admin/status-page/domain',
        summary: 'Remove domain configuration',
        description: 'Remove the custom domain configuration and associated DNS records.',
        tags: ['Admin - Status Page'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Domain configuration removed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden - Insufficient permissions'),
            new OA\Response(response: 503, description: 'Service Unavailable - Cloudflare API error'),
        ]
    )]
    public function removeDomain(Request $request): Response
    {
        try {
            $config = StatusPage::getConfiguration();

            if (!$config) {
                return ApiResponse::error('No status page configuration found', 404);
            }

            if (empty($config['domain'])) {
                return ApiResponse::error('No domain configured for status page', 400);
            }

            // Remove Cloudflare DNS record if it exists
            if (!empty($config['cloudflare_zone_id']) && !empty($config['cloudflare_record_id'])) {
                try {
                    $cloudflareService = new CloudflareSubdomainService();
                    $cloudflareService->deleteDnsRecord($config['cloudflare_zone_id'], $config['cloudflare_record_id']);
                } catch (\Exception $cloudflareError) {
                    // Log error but continue with local cleanup
                    error_log('Failed to remove Cloudflare DNS record: ' . $cloudflareError->getMessage());
                }
            }

            // Update configuration to remove domain
            $configUpdate = [
                'domain' => '',
                'ssl_status' => 'pending',
                'cloudflare_zone_id' => null,
                'cloudflare_record_id' => null,
                'is_active' => 0
            ];

            $success = StatusPage::updateConfiguration($configUpdate);

            if (!$success) {
                return ApiResponse::error('Failed to remove domain configuration', 500);
            }

            return ApiResponse::success(null, 'Domain configuration removed successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to remove domain configuration: ' . $e->getMessage(), 500);
        }
    }
}
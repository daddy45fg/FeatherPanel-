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

namespace App\Models;

use App\App;
use App\Chat\Database;

/**
 * StatusPage model for managing the public status page configuration.
 *
 * This class provides database operations for the status_pages table,
 * handling configuration of the public Wings node status page.
 */
class StatusPage
{
    private static string $table = 'status_pages';

    /**
     * Get the current status page configuration (or create default if none exists).
     */
    public static function getConfiguration(): ?array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' LIMIT 1');
        $stmt->execute();

        $config = $stmt->fetch(\PDO::FETCH_ASSOC);

        // If no configuration exists, create a default one
        if (!$config) {
            return self::createDefaultConfiguration();
        }

        return $config;
    }

    /**
     * Get the active status page configuration.
     */
    public static function getActiveConfiguration(): ?array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE is_active = 1 LIMIT 1');
        $stmt->execute();

        $config = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $config ?: null;
    }

    /**
     * Get status page configuration by UUID.
     */
    public static function getConfigurationByUuid(string $uuid): ?array
    {
        if (!self::isValidUuid($uuid)) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE uuid = :uuid LIMIT 1');
        $stmt->execute(['uuid' => $uuid]);

        $config = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $config ?: null;
    }

    /**
     * Update the status page configuration.
     */
    public static function updateConfiguration(array $data): bool
    {
        $existing = self::getConfiguration();
        if (!$existing) {
            return self::createConfiguration($data);
        }

        $pdo = Database::getPdoConnection();

        try {
            $pdo->beginTransaction();

            $fields = [];
            $params = ['uuid' => $existing['uuid']];

            if (isset($data['domain']) && trim((string) $data['domain']) !== '') {
                if (!self::isValidDomain(trim((string) $data['domain']))) {
                    throw new \InvalidArgumentException('Invalid domain format');
                }
                $fields[] = 'domain = :domain';
                $params['domain'] = trim((string) $data['domain']);
            }

            if (isset($data['title']) && trim((string) $data['title']) !== '') {
                $fields[] = 'title = :title';
                $params['title'] = trim((string) $data['title']);
            }

            if (isset($data['company_name']) && trim((string) $data['company_name']) !== '') {
                $fields[] = 'company_name = :company_name';
                $params['company_name'] = trim((string) $data['company_name']);
            }

            if (isset($data['support_email'])) {
                $email = trim((string) $data['support_email']);
                if ($email !== '' && !self::isValidEmail($email)) {
                    throw new \InvalidArgumentException('Invalid email format');
                }
                $fields[] = 'support_email = :support_email';
                $params['support_email'] = $email ?: null;
            }

            if (array_key_exists('is_active', $data)) {
                // Ensure only one record is active
                if ((int) $data['is_active'] === 1) {
                    $pdo->prepare('UPDATE ' . self::$table . ' SET is_active = 0')->execute();
                }
                $fields[] = 'is_active = :is_active';
                $params['is_active'] = (int) $data['is_active'] === 0 ? 0 : 1;
            }

            if (array_key_exists('show_node_names', $data)) {
                $fields[] = 'show_node_names = :show_node_names';
                $params['show_node_names'] = (int) $data['show_node_names'] === 0 ? 0 : 1;
            }

            if (array_key_exists('show_resource_usage', $data)) {
                $fields[] = 'show_resource_usage = :show_resource_usage';
                $params['show_resource_usage'] = (int) $data['show_resource_usage'] === 0 ? 0 : 1;
            }

            if (array_key_exists('show_locations', $data)) {
                $fields[] = 'show_locations = :show_locations';
                $params['show_locations'] = (int) $data['show_locations'] === 0 ? 0 : 1;
            }

            if (array_key_exists('auto_refresh_enabled', $data)) {
                $fields[] = 'auto_refresh_enabled = :auto_refresh_enabled';
                $params['auto_refresh_enabled'] = (int) $data['auto_refresh_enabled'] === 0 ? 0 : 1;
            }

            if (isset($data['auto_refresh_interval']) && (int) $data['auto_refresh_interval'] > 0) {
                $interval = max(5, min(300, (int) $data['auto_refresh_interval'])); // 5s to 5min
                $fields[] = 'auto_refresh_interval = :auto_refresh_interval';
                $params['auto_refresh_interval'] = $interval;
            }

            if (array_key_exists('ssl_status', $data)) {
                $validStatuses = ['pending', 'active', 'failed'];
                if (in_array($data['ssl_status'], $validStatuses, true)) {
                    $fields[] = 'ssl_status = :ssl_status';
                    $params['ssl_status'] = $data['ssl_status'];
                }
            }

            if (array_key_exists('cloudflare_zone_id', $data)) {
                $fields[] = 'cloudflare_zone_id = :cloudflare_zone_id';
                $params['cloudflare_zone_id'] = $data['cloudflare_zone_id'] !== null ? trim((string) $data['cloudflare_zone_id']) : null;
            }

            if (array_key_exists('cloudflare_record_id', $data)) {
                $fields[] = 'cloudflare_record_id = :cloudflare_record_id';
                $params['cloudflare_record_id'] = $data['cloudflare_record_id'] !== null ? trim((string) $data['cloudflare_record_id']) : null;
            }

            if (!empty($fields)) {
                $sql = 'UPDATE ' . self::$table . ' SET ' . implode(', ', $fields) . ' WHERE uuid = :uuid';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            $pdo->commit();

            return true;
        } catch (\PDOException | \InvalidArgumentException $exception) {
            $pdo->rollBack();
            App::getInstance(true)->getLogger()->error('Failed to update status page configuration: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Create a new status page configuration.
     */
    public static function createConfiguration(array $data): bool
    {
        $pdo = Database::getPdoConnection();

        try {
            $pdo->beginTransaction();

            // Ensure only one active record
            if (isset($data['is_active']) && (int) $data['is_active'] === 1) {
                $pdo->prepare('UPDATE ' . self::$table . ' SET is_active = 0')->execute();
            }

            $configData = [
                'uuid' => self::generateUuid(),
                'domain' => isset($data['domain']) ? trim((string) $data['domain']) : '',
                'title' => isset($data['title']) ? trim((string) $data['title']) : 'FeatherPanel Status',
                'company_name' => isset($data['company_name']) ? trim((string) $data['company_name']) : 'FeatherPanel',
                'support_email' => isset($data['support_email']) ? trim((string) $data['support_email']) : null,
                'is_active' => isset($data['is_active']) && (int) $data['is_active'] === 0 ? 0 : 1,
                'show_node_names' => isset($data['show_node_names']) && (int) $data['show_node_names'] === 0 ? 0 : 1,
                'show_resource_usage' => isset($data['show_resource_usage']) && (int) $data['show_resource_usage'] === 0 ? 0 : 1,
                'show_locations' => isset($data['show_locations']) && (int) $data['show_locations'] === 1 ? 1 : 0,
                'auto_refresh_enabled' => isset($data['auto_refresh_enabled']) && (int) $data['auto_refresh_enabled'] === 0 ? 0 : 1,
                'auto_refresh_interval' => isset($data['auto_refresh_interval']) && (int) $data['auto_refresh_interval'] > 0 ? max(5, min(300, (int) $data['auto_refresh_interval'])) : 30,
                'ssl_status' => 'pending',
                'cloudflare_zone_id' => null,
                'cloudflare_record_id' => null,
            ];

            // Validate domain and email if provided
            if (!empty($configData['domain']) && !self::isValidDomain($configData['domain'])) {
                throw new \InvalidArgumentException('Invalid domain format');
            }

            if (!empty($configData['support_email']) && !self::isValidEmail($configData['support_email'])) {
                throw new \InvalidArgumentException('Invalid email format');
            }

            $stmt = $pdo->prepare('
                INSERT INTO ' . self::$table . ' (
                    uuid, domain, title, company_name, support_email, is_active,
                    show_node_names, show_resource_usage, show_locations,
                    auto_refresh_enabled, auto_refresh_interval, ssl_status,
                    cloudflare_zone_id, cloudflare_record_id
                ) VALUES (
                    :uuid, :domain, :title, :company_name, :support_email, :is_active,
                    :show_node_names, :show_resource_usage, :show_locations,
                    :auto_refresh_enabled, :auto_refresh_interval, :ssl_status,
                    :cloudflare_zone_id, :cloudflare_record_id
                )
            ');
            $stmt->execute($configData);

            $pdo->commit();

            return true;
        } catch (\PDOException | \InvalidArgumentException $exception) {
            $pdo->rollBack();
            App::getInstance(true)->getLogger()->error('Failed to create status page configuration: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Create a default configuration if none exists.
     */
    private static function createDefaultConfiguration(): ?array
    {
        $defaultData = [
            'domain' => '',
            'title' => 'FeatherPanel Status',
            'company_name' => 'FeatherPanel',
            'support_email' => null,
            'is_active' => 0,
            'show_node_names' => 1,
            'show_resource_usage' => 1,
            'show_locations' => 0,
            'auto_refresh_enabled' => 1,
            'auto_refresh_interval' => 30,
        ];

        if (self::createConfiguration($defaultData)) {
            return self::getConfiguration();
        }

        return null;
    }

    /**
     * Get status page configuration by domain.
     */
    public static function getConfigurationByDomain(string $domain): ?array
    {
        if (empty($domain)) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE domain = :domain AND is_active = 1 LIMIT 1');
        $stmt->execute(['domain' => $domain]);

        $config = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $config ?: null;
    }

    /**
     * Update Cloudflare zone and record IDs for a domain.
     */
    public static function updateCloudflareIds(string $uuid, ?string $zoneId, ?string $recordId): bool
    {
        if (!self::isValidUuid($uuid)) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('
            UPDATE ' . self::$table . '
            SET cloudflare_zone_id = :zone_id, cloudflare_record_id = :record_id
            WHERE uuid = :uuid
        ');

        return $stmt->execute([
            'zone_id' => $zoneId,
            'record_id' => $recordId,
            'uuid' => $uuid,
        ]);
    }

    /**
     * Update SSL status for the status page.
     */
    public static function updateSslStatus(string $uuid, string $status): bool
    {
        if (!self::isValidUuid($uuid)) {
            return false;
        }

        $validStatuses = ['pending', 'active', 'failed'];
        if (!in_array($status, $validStatuses, true)) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('UPDATE ' . self::$table . ' SET ssl_status = :status WHERE uuid = :uuid');

        return $stmt->execute([
            'status' => $status,
            'uuid' => $uuid,
        ]);
    }

    /**
     * Generate UUID v4.
     */
    public static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * Validate email format.
     */
    private static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate domain format.
     */
    private static function isValidDomain(string $domain): bool
    {
        // Basic domain validation - can be enhanced for subdomains
        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain);
    }

    /**
     * Basic UUID format validation.
     */
    private static function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[a-f0-9\-]{36}$/i', $uuid);
    }
}
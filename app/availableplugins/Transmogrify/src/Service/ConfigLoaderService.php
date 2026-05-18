<?php
/**
 * COmanage Registry Config Loader Service
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link          https://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace Transmogrify\Service;

final class ConfigLoaderService
{
    /**
     * Load the tables config JSON and extend it with target schema (schema.json) and source schema (schema.xml).
     *
     * @param string $path Path to tables.json (can be relative to project root or absolute)
     * @return array The effective tables configuration
     */
    public function load(string $path): array
    {
        $cfg = $this->loadGeneric($path);
        // Filter out documentation or comment keys (eg, keys starting with "__")
        $cfg = array_filter($cfg, static function ($value, $key) {
            return !(is_string($key) && str_starts_with($key, '__'));
        }, ARRAY_FILTER_USE_BOTH);
        return $cfg;
    }

    /**
     * Generic config loader that supports JSON (.json) and XML (.xml) files.
     * Returns associative array representation of the root object.
     *
     * @param string $path Absolute or project-relative path
     * @return array
     */
    public function loadGeneric(string $path): array
    {
        // Resolve path if relative
        if (!is_readable($path)) {
            if (defined('ROOT')) {
                $alt = ROOT . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
                if (is_readable($alt)) {
                    $path = $alt;
                }
            }
        }
        if (!is_readable($path)) {
            throw new \RuntimeException('Config not readable: ' . $path);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Failed to read config: ' . $path);
        }

        if ($ext === 'json') {
            // Decode JSON and detect corruption with detailed error messages
            $data = json_decode($raw, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $err = function_exists('json_last_error_msg') ? json_last_error_msg() : ('code ' . json_last_error());
                throw new \RuntimeException('Invalid JSON config: ' . $path . ' Details: ' . $err);
            }
            if (!is_array($data)) {
                // Enforce that root must be an object/array for config purposes
                throw new \RuntimeException('Invalid JSON config: ' . $path . ' Details: Expected root object or array');
            }
            return $data;
        }

        if ($ext === 'xml') {
            // Use libxml internal error handling instead of deprecated @ suppression
            $prev = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                libxml_use_internal_errors($prev);
                $messages = array_map(static function ($e) {
                    return trim($e->message ?? '') . ' at line ' . ($e->line ?? '');
                }, $errors ?: []);
                $detail = $messages ? (' Details: ' . implode(' | ', $messages)) : '';
                throw new \RuntimeException('Invalid XML config: ' . $path . $detail);
            }
            // Clear any accumulated libxml errors and restore previous setting
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            $json = json_encode($xml);
            $arr = json_decode($json, true);
            if (!is_array($arr)) {
                throw new \RuntimeException('Failed to convert XML to array: ' . $path);
            }
            return $arr;
        }

        throw new \RuntimeException('Unsupported config extension (expected .json or .xml): ' . $path);
    }
}

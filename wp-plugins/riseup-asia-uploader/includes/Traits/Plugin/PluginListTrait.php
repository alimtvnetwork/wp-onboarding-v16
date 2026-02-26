<?php
/**
 * PluginListTrait — Plugin listing, file scanning, and file content retrieval.
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Upload\UploadIgnore;
use RiseupAsia\Database\FileCache;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait PluginListTrait
{
    /**
     * Handle list plugins.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handleListPlugins($request) {
        $this->fileLogger->info('List plugins endpoint called');

        try {
            if (BooleanHelpers::isFuncMissing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugins = $this->collectPluginList();

            return EnvelopeBuilder::success()
                ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Plugins->route())
                ->setResults($plugins)
                ->toResponse();
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'List plugins error');

            return $this->errorResponse('Failed to list plugins: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    /**
     * Handle plugin info — return details for a single plugin by slug.
     *
     * @param WP_REST_Request $request Request object (expects 'slug' in JSON body).
     * @return WP_REST_Response
     */
    public function handlePluginInfo(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $body = $request->get_json_params();
            $slug = isset($body['slug']) ? sanitize_text_field($body['slug']) : '';

            if (empty($slug)) {

                return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
            }

            if (BooleanHelpers::isFuncMissing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $allPlugins    = get_plugins();
            $activePlugins = get_option(OptionNameType::ActivePlugins->value, array());

            foreach ($allPlugins as $pluginFile => $pluginData) {
                $pluginSlug = dirname($pluginFile);
                if ($pluginSlug === '.') {
                    $pluginSlug = basename($pluginFile, '.php');
                }

                if ($pluginSlug === $slug) {

                    return EnvelopeBuilder::success()
                        ->autoDetectRequestedAt()
                        ->setSingleResult(array(
                            ResponseKeyType::Slug->value        => $pluginSlug,
                            ResponseKeyType::Name->value        => $pluginData['Name'],
                            ResponseKeyType::Version->value     => $pluginData['Version'],
                            ResponseKeyType::Author->value      => $pluginData['Author'],
                            ResponseKeyType::Description->value => $pluginData['Description'],
                            ResponseKeyType::Active->value      => in_array($pluginFile, $activePlugins, true),
                            ResponseKeyType::PluginFile->value  => $pluginFile,
                        ))
                        ->toResponse();
                }
            }

            return $this->errorResponse(ResponseMessageType::PluginNotFound->value . ': ' . $slug, HttpStatusType::NotFound->value);
        }, 'plugin_info');
    }

     * Collect all installed plugins into a normalized array.
     *
     * @return array Plugin list.
     */
    private function collectPluginList(): array {
        $allPlugins    = get_plugins();
        $activePlugins = get_option(OptionNameType::ActivePlugins->value, array());
        $plugins       = array();

        foreach ($allPlugins as $pluginFile => $pluginData) {
            $slug = dirname($pluginFile);
            if ($slug === '.') {
                $slug = basename($pluginFile, '.php');
            }

            $plugins[] = array(
                ResponseKeyType::Slug->value        => $slug,
                ResponseKeyType::Name->value        => $pluginData['Name'],
                ResponseKeyType::Version->value     => $pluginData['Version'],
                ResponseKeyType::Author->value      => $pluginData['Author'],
                ResponseKeyType::Description->value => $pluginData['Description'],
                ResponseKeyType::Active->value      => in_array($pluginFile, $activePlugins, true),
                ResponseKeyType::PluginFile->value  => $pluginFile,
            );
        }

        return $plugins;
    }

    /**
     * Handle plugin files listing (for diff preview).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handlePluginFiles($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');

        if (empty($slug)) {

            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }

        try {

            return $this->scanPluginFilesWithCache($slug);
        } catch (Throwable $e) {

            return $this->errorResponse('Failed to list plugin files: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    /**
     * Scan plugin files using the file cache and return the response.
     *
     * @param string $slug Plugin slug.
     * @return WP_REST_Response
     */
    private function scanPluginFilesWithCache(string $slug) {
        if (BooleanHelpers::isFuncMissing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;

        if (PathHelper::isDirMissing($pluginDir)) {

            return $this->errorResponse(ResponseMessageType::PluginNotFound->value . ': ' . $slug, HttpStatusType::NotFound->value);
        }

        $ignore = UploadIgnore::fromDirectory($pluginDir);
        $fileCache = FileCache::getInstance($this->fileLogger, $this->db);
        $result = $fileCache->getManifest($slug, $pluginDir, $ignore);

        return new WP_REST_Response(array(
            ResponseKeyType::Success->value    => true,
            ResponseKeyType::Plugin->value     => $slug,
            ResponseKeyType::TotalFiles->value => count($result[ResponseKeyType::Files->value]),
            ResponseKeyType::Files->value      => $result[ResponseKeyType::Files->value],
        ), HttpStatusType::Ok->value);
    }

    /**
     * Handle getting content of a single file from a plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handlePluginFileContent($request) {
        $json = $request->get_json_params();
        $slug = isset($json['plugin']) ? sanitize_text_field($json['plugin']) : $request->get_param('slug');

        if (empty($slug)) {

            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }

        try {
            $filePath = isset($json[ResponseKeyType::Path->value]) ? $json[ResponseKeyType::Path->value] : null;

            $validation = $this->validateFilePath($filePath, $slug);
            if ($validation instanceof WP_REST_Response) {

                return $validation;
            }

            return $this->readAndReturnFile($validation[ResponseKeyType::RealPath->value], $validation[ResponseKeyType::FilePath->value]);
        } catch (Throwable $e) {

            return $this->errorResponse('Failed to read file: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    /**
     * Validate and resolve a plugin file path.
     *
     * @param string|null $filePath Relative file path.
     * @param string      $slug     Plugin slug.
     * @return array{realPath: string, filePath: string}|WP_REST_Response
     */
    private function validateFilePath($filePath, string $slug) {
        if (empty($filePath)) {

            return $this->errorResponse('File path is required', HttpStatusType::BadRequest->value);
        }

        $filePath = ltrim($filePath, '/\\');

        if (strpos($filePath, '..') !== false) {

            return $this->errorResponse('Invalid file path', HttpStatusType::BadRequest->value);
        }

        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;
        if (PathHelper::isDirMissing($pluginDir)) {

            return $this->errorResponse(ResponseMessageType::PluginNotFound->value . ': ' . $slug, HttpStatusType::NotFound->value);
        }

        $realPluginDir = realpath($pluginDir);
        $realFilePath = realpath($pluginDir . '/' . $filePath);

        if ($realFilePath === false || strpos($realFilePath, $realPluginDir) !== 0) {

            return $this->errorResponse('File not found or invalid path', HttpStatusType::NotFound->value);
        }

        if (BooleanHelpers::isIrregularPath($realFilePath)) {

            return $this->errorResponse('File not found', HttpStatusType::NotFound->value);
        }

        return array(ResponseKeyType::RealPath->value => $realFilePath, ResponseKeyType::FilePath->value => $filePath);
    }

    /**
     * Read a file and return its content as a REST response.
     *
     * @param string $realPath Absolute file path.
     * @param string $relPath  Relative file path.
     * @return WP_REST_Response
     */
    private function readAndReturnFile(string $realPath, string $relPath) {
        $content = @file_get_contents($realPath);
        if ($content === false) {

            return $this->errorResponse('Failed to read file', HttpStatusType::ServerError->value);
        }

        return new WP_REST_Response(array(
            ResponseKeyType::Success->value => true,
            ResponseKeyType::Path->value    => $relPath,
            ResponseKeyType::Content->value => $content,
        ), HttpStatusType::Ok->value);
    }
}

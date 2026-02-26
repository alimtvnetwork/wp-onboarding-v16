<?php
/**
 * Upload Validator class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardUploadValidator
 *
 * Validates plugin ZIP uploads for security.
 */
class OnboardUploadValidator {

    /**
     * Allowed MIME types.
     */
    const ALLOWED_MIMETYPES = array('application/zip', 'application/x-zip-compressed', 'application/octet-stream');

    /**
     * ZIP magic bytes.
     */
    const ZIP_MAGIC_BYTES = "PK\x03\x04";

    /**
     * Malicious PHP patterns.
     */
    const MALICIOUS_PATTERNS = array(
        '/\beval\s*\(/i',
        '/\bbase64_decode\s*\([^)]*\$/',  // base64_decode with variable input
        '/\bsystem\s*\(/i',
        '/\bexec\s*\(/i',
        '/\bpassthru\s*\(/i',
        '/\bshell_exec\s*\(/i',
        '/\bpopen\s*\(/i',
        '/\bproc_open\s*\(/i',
        '/\bcreate_function\s*\(/i',
        '/\bassert\s*\(/i',
        '/\bpreg_replace\s*\([^,]*\/[^,]*e[^,]*,/i',  // preg_replace with /e modifier
        '/\$_(?:GET|POST|REQUEST|COOKIE)\s*\[.*\]\s*\(/i',  // Direct execution of superglobals
    );

    /**
     * Validate uploaded file.
     *
     * @param array $file Uploaded file data ($_FILES format).
     * @return true|WP_Error
     */
    public function validate($file) {
        // Check for upload errors.
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file['error']), array('status' => 400));
        }

        // Check file exists.
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return new WP_Error('file_missing', 'Uploaded file not found', array('status' => 400));
        }

        // Check MIME type.
        $mime_type = isset($file['type']) ? $file['type'] : '';
        $finfo_mime = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']) : $mime_type;

        if (!in_array($mime_type, self::ALLOWED_MIMETYPES, true) && !in_array($finfo_mime, self::ALLOWED_MIMETYPES, true)) {
            return new WP_Error('invalid_mime_type', 'Invalid file type. Only ZIP files are allowed.', array('status' => 400));
        }

        // Check magic bytes.
        $handle = fopen($file['tmp_name'], 'rb');
        $magic = fread($handle, 4);
        fclose($handle);

        if ($magic !== self::ZIP_MAGIC_BYTES) {
            return new WP_Error('invalid_zip', 'File is not a valid ZIP archive', array('status' => 400));
        }

        // Check file size.
        if ($file['size'] > ONBOARD_MAX_UPLOAD_SIZE) {
            return new WP_Error(
                'file_too_large',
                'File is too large. Maximum size: ' . size_format(ONBOARD_MAX_UPLOAD_SIZE),
                array('status' => 400)
            );
        }

        // Validate ZIP structure.
        $structure_validation = $this->validate_zip_structure($file['tmp_name']);

        if (is_wp_error($structure_validation)) {
            return $structure_validation;
        }

        // Scan for malicious code.
        $security_scan = $this->scan_for_malicious_code($file['tmp_name']);

        if (is_wp_error($security_scan)) {
            return $security_scan;
        }

        return true;
    }

    /**
     * Validate ZIP structure.
     *
     * @param string $zip_path Path to ZIP file.
     * @return true|WP_Error
     */
    private function validate_zip_structure($zip_path) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_not_available', 'ZipArchive class not available', array('status' => 500));
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('zip_open_failed', 'Failed to open ZIP file', array('status' => 400));
        }

        // Check for plugin folder and valid structure.
        $has_plugin_folder = false;
        $root_folders = array();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Check for path traversal attempts.
            if (strpos($filename, '..') !== false || strpos($filename, '//') !== false) {
                $zip->close();
                return new WP_Error('path_traversal', 'ZIP contains invalid path: ' . $filename, array('status' => 400));
            }

            // Get root folder.
            $parts = explode('/', $filename);
            if (!empty($parts[0]) && !in_array($parts[0], $root_folders, true)) {
                $root_folders[] = $parts[0];
            }

            // Check for PHP files with plugin headers.
            if (substr($filename, -4) === '.php' && count($parts) === 2) {
                $content = $zip->getFromIndex($i);
                if (strpos($content, 'Plugin Name:') !== false) {
                    $has_plugin_folder = true;
                }
            }
        }

        $zip->close();

        // Should have exactly one root folder.
        if (count($root_folders) !== 1) {
            return new WP_Error(
                'invalid_structure',
                'ZIP must contain exactly one plugin folder at the root level',
                array('status' => 400)
            );
        }

        if (!$has_plugin_folder) {
            return new WP_Error(
                'no_plugin_header',
                'ZIP does not contain a valid WordPress plugin (missing Plugin Name header)',
                array('status' => 400)
            );
        }

        return true;
    }

    /**
     * Scan ZIP for malicious code.
     *
     * @param string $zip_path Path to ZIP file.
     * @return true|WP_Error
     */
    private function scan_for_malicious_code($zip_path) {
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('zip_open_failed', 'Failed to open ZIP file for scanning', array('status' => 500));
        }

        $suspicious_files = array();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Only scan PHP files.
            if (substr($filename, -4) !== '.php') {
                continue;
            }

            $content = $zip->getFromIndex($i);

            foreach (self::MALICIOUS_PATTERNS as $pattern) {
                if (preg_match($pattern, $content)) {
                    $suspicious_files[] = $filename;
                    break;
                }
            }
        }

        $zip->close();

        if (!empty($suspicious_files)) {
            return new WP_Error(
                'malicious_code_detected',
                'Potentially malicious code detected in: ' . implode(', ', array_slice($suspicious_files, 0, 5)) .
                (count($suspicious_files) > 5 ? ' and ' . (count($suspicious_files) - 5) . ' more files' : ''),
                array('status' => 400)
            );
        }

        return true;
    }

    /**
     * Get plugin info from ZIP.
     *
     * @param string $zip_path Path to ZIP file.
     * @return array|WP_Error
     */
    public function get_plugin_info($zip_path) {
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('zip_open_failed', 'Failed to open ZIP file', array('status' => 500));
        }

        $plugin_info = null;
        $root_folder = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $parts = explode('/', $filename);

            // Get root folder name.
            if ($root_folder === null && !empty($parts[0])) {
                $root_folder = $parts[0];
            }

            // Look for PHP files in root of plugin folder.
            if (substr($filename, -4) === '.php' && count($parts) === 2) {
                $content = $zip->getFromIndex($i);

                // Parse plugin headers.
                $headers = $this->parse_plugin_headers($content);
                if (!empty($headers['Name'])) {
                    $plugin_info = array(
                        'slug' => $root_folder,
                        'name' => $headers['Name'],
                        'version' => isset($headers['Version']) ? $headers['Version'] : '0.0.0',
                        'description' => isset($headers['Description']) ? $headers['Description'] : '',
                        'author' => isset($headers['Author']) ? $headers['Author'] : '',
                        'main_file' => basename($filename),
                    );
                    break;
                }
            }
        }

        $zip->close();

        if (!$plugin_info) {
            return new WP_Error('no_plugin_info', 'Could not extract plugin information from ZIP', array('status' => 400));
        }

        return $plugin_info;
    }

    /**
     * Parse plugin headers from content.
     *
     * @param string $content File content.
     * @return array
     */
    private function parse_plugin_headers($content) {
        $headers = array(
            'Name' => 'Plugin Name',
            'PluginURI' => 'Plugin URI',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author',
            'AuthorURI' => 'Author URI',
            'TextDomain' => 'Text Domain',
            'DomainPath' => 'Domain Path',
            'RequiresWP' => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
        );

        $result = array();

        foreach ($headers as $field => $regex) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':\s*(.+)$/mi', $content, $match)) {
                $result[$field] = trim($match[1]);
            }
        }

        return $result;
    }

    /**
     * Get upload error message.
     *
     * @param int $error_code PHP upload error code.
     * @return string
     */
    private function get_upload_error_message($error_code) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        );

        return isset($messages[$error_code]) ? $messages[$error_code] : 'Unknown upload error';
    }
}

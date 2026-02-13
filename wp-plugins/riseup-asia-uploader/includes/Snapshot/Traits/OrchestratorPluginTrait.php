<?php
/**
 * OrchestratorPluginTrait — Plugin snapshot creation and archival.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait OrchestratorPluginTrait {

    /**
     * Snapshot installed WordPress plugins as ZIP files.
     *
     * @param string $snapshot_dir Snapshot directory.
     * @param string $selection    'all' or 'selective'.
     * @return array Stats: count, total_size, plugins[].
     */
    private function snapshotPlugins($snapshot_dir, $selection = 'all') {
        $plugins_dir = $snapshot_dir . '/plugins';
        if (!RiseupPathUtils::ensure_dir($plugins_dir, true)) {
            $this->log('ERROR', 'Failed to create plugins directory');
            return array('count' => 0, 'total_size' => 0, 'plugins' => array());
        }

        $plugins_to_snapshot = $this->collectPluginsToSnapshot($selection);
        $rootPdo = $this->openRootDbForPlugins($snapshot_dir);

        $count = 0;
        $total_size = 0;
        $plugin_list = array();

        foreach ($plugins_to_snapshot as $plugin_file => $info) {
            $result = $this->archiveSinglePlugin($info, $plugins_dir, $rootPdo);
            if ($result === null) continue;
            if ($result['success']) {
                $total_size += $result['size'];
                $count++;
                $plugin_list[] = $result['entry'];
            }
        }

        $rootPdo = null;
        return array('count' => $count, 'total_size' => $total_size, 'plugins' => $plugin_list);
    }

    /** Collect plugins eligible for snapshotting. */
    private function collectPluginsToSnapshot($selection) {
        if (RiseupBooleanHelpers::is_func_missing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugins_to_snapshot = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }
            if ($slug === PLUGIN_SLUG) continue;

            $isEligible = ($selection === 'all' || in_array($plugin_file, $active_plugins));
            if (!$isEligible) continue;

            $plugins_to_snapshot[$plugin_file] = array(
                'slug' => $slug, 'name' => $plugin_data['Name'] ?? $slug, 'version' => $plugin_data['Version'] ?? '0.0.0',
            );
        }

        $this->log('INFO', 'Snapshotting plugins', array('total' => count($all_plugins), 'selected' => count($plugins_to_snapshot), 'selection' => $selection));
        return $plugins_to_snapshot;
    }

    /** Archive a single plugin as ZIP. */
    private function archiveSinglePlugin($info, $plugins_dir, $rootPdo) {
        $slug = $info['slug'];
        $plugin_path = WP_PLUGIN_DIR . '/' . $slug;

        if (RiseupBooleanHelpers::is_dir_missing($plugin_path)) {
            $this->log('INFO', 'Skipping single-file plugin: ' . $slug);
            return null;
        }

        $zip_filename = $slug . '.zip';
        $zip_path = $plugins_dir . '/' . $zip_filename;
        $zip_result = $this->createPluginZip($plugin_path, $zip_path, $slug);

        if (!$zip_result['success']) {
            $this->log('WARN', 'Failed to archive plugin: ' . $slug, array('error' => $zip_result['error']));
            return array('success' => false);
        }

        $entry = array('slug' => $info['slug'], 'name' => $info['name'], 'version' => $info['version'], 'zip' => $zip_filename, 'size' => filesize($zip_path));

        if ($rootPdo) {
            $this->rootDb->registerPluginSnapshot($rootPdo, array(
                'plugin_slug' => $info['slug'], 'plugin_name' => $info['name'], 'plugin_version' => $info['version'],
                'zip_file' => 'plugins/' . $zip_filename, 'file_size_bytes' => filesize($zip_path), 'checksum_md5' => md5_file($zip_path),
            ));
        }

        $this->log('INFO', sprintf('Plugin archived: %s (%s)', $info['name'], $this->formatBytes($entry['size'])));
        return array('success' => true, 'size' => $entry['size'], 'entry' => $entry);
    }

    /** Create a ZIP from a plugin directory. */
    private function createPluginZip($source_dir, $zip_path, $slug) {
        try {
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return array('success' => false, 'error' => 'Failed to create ZIP');
            }

            $source_dir = rtrim($source_dir, '/\\\\');
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $relative = $slug . '/' . substr($item->getPathname(), strlen($source_dir) + 1);
                $relative = str_replace('\\', '/', $relative);
                if ($item->isDir()) {
                    $zip->addEmptyDir($relative);
                } else {
                    $zip->addFile($item->getPathname(), $relative);
                }
            }

            $zip->close();

            $size = filesize($zip_path);
            if ($size === 0) {
                @unlink($zip_path);
                return array('success' => false, 'error' => 'ZIP file is empty');
            }

            return array('success' => true);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /** Open a-root.db for plugin registration. */
    private function openRootDbForPlugins(string $snapshotDir): ?PDO {
        $root_path = $snapshotDir . '/a-root.db';
        if (RiseupBooleanHelpers::is_file_missing($root_path)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $root_path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (Exception $e) {
            $this->log('WARN', 'Could not open a-root.db for plugin registration', array('error' => $e->getMessage()));
            return null;
        }
    }
}

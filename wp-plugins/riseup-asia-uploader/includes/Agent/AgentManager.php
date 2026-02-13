<?php
/**
 * Riseup Asia Uploader - Agent Manager
 *
 * Manages agent sites for multi-site orchestration (master-agent architecture).
 * Enables remote control of plugins on other WordPress sites.
 *
 * @package RiseupAsiaUploader
 * @since   1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseupAgentManager
 *
 * Handles CRUD operations for agent sites and remote plugin control.
 */
class RiseupAgentManager {

    /**
     * Encryption key for app passwords (derived from WordPress salts).
     */
    private $encryption_key;

    /**
     * File logger instance.
     *
     * @var RiseupFileLogger
     */
    private $file_logger;

    /**
     * Database instance.
     *
     * @var RiseupDatabase
     */
    private $db;

    /**
     * Singleton instance.
     *
     * @var RiseupAgentManager|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseupAgentManager
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->file_logger = RiseupFileLogger::get_instance();
        $this->db = RiseupDatabase::get_instance();
        
        // Derive encryption key from WordPress salts
        $this->encryption_key = substr(hash('sha256', AUTH_KEY . SECURE_AUTH_KEY), 0, 32);
    }

    // =========================================================================
    // ENCRYPTION HELPERS
    // =========================================================================

    /**
     * Encrypt a string using AES-256-GCM.
     *
     * @param string $plaintext The plaintext to encrypt.
     * @return string Base64-encoded ciphertext with IV and tag.
     */
    private function encrypt($plaintext) {
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        
        // Combine IV + tag + ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a string encrypted with AES-256-GCM.
     *
     * @param string $encrypted Base64-encoded ciphertext.
     * @return string|false Decrypted plaintext or false on failure.
     */
    private function decrypt($encrypted) {
        $data = base64_decode($encrypted);
        
        if (strlen($data) < 28) { // 12 IV + 16 tag minimum
            return false;
        }
        
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        
        return openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }

    // =========================================================================
    // AGENT SITE CRUD
    // =========================================================================

    /**
     * Add a new agent site.
     *
     * @param array $data Agent data (name, url, username, app_password, redirect_url).
     * @return int|WP_Error Agent ID on success, WP_Error on failure.
     */
    public function addAgent($data) {
        $this->file_logger->info('Adding agent site', array('name' => $data['name'], 'url' => $data['url']));
        
        // Validate required fields
        if (empty($data['name']) || empty($data['url']) || empty($data['username']) || empty($data['app_password'])) {
            return new WP_Error('missing_fields', 'Name, URL, username, and application password are required');
        }
        
        // Normalize URL
        $url = $this->normalizeUrl($data['url']);
        
        // Encrypt the app password
        $encrypted_password = $this->encrypt($data['app_password']);
        
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return new WP_Error('db_error', 'Database not available');
            }
            
            $stmt = $pdo->prepare("INSERT INTO agent_sites 
                (name, url, username, app_password_encrypted, redirect_url, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            
            $stmt->execute(array(
                sanitize_text_field($data['name']),
                esc_url_raw($url),
                sanitize_user($data['username']),
                $encrypted_password,
                isset($data['redirect_url']) ? esc_url_raw($data['redirect_url']) : null,
                gmdate('Y-m-d\TH:i:s\Z'),
            ));
            
            $agent_id = (int) $pdo->lastInsertId();
            $this->file_logger->info('Agent site added', array('id' => $agent_id));
            
            return $agent_id;
            
        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to add agent site');
            return new WP_Error('db_error', 'Failed to save agent site: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing agent site.
     *
     * @param int   $id   Agent ID.
     * @param array $data Updated data.
     * @return bool|WP_Error True on success.
     */
    public function updateAgent($id, $data) {
        $this->file_logger->info('Updating agent site', array('id' => $id));
        
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return new WP_Error('db_error', 'Database not available');
            }
            
            $update = $this->buildUpdateSets($data);
            if (empty($update['sets'])) {
                return new WP_Error('no_data', 'No fields to update');
            }
            
            $update['sets'][] = 'updated_at = ?';
            $update['params'][] = gmdate('Y-m-d\TH:i:s\Z');
            $update['params'][] = (int) $id;
            
            $sql = "UPDATE agent_sites SET " . implode(', ', $update['sets']) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($update['params']);
            
            $this->file_logger->info('Agent site updated', array('id' => $id));

            return true;
            
        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to update agent site');

            return new WP_Error('db_error', 'Failed to update agent site: ' . $e->getMessage());
        }
    }

    /**
     * Build SET clauses and params from update data.
     *
     * @param array $data Update data.
     * @return array{sets: string[], params: array} SET clauses and params.
     */
    private function buildUpdateSets(array $data): array {
        $sets = array();
        $params = array();

        $field_map = array(
            'name'         => fn($v) => array('name = ?', sanitize_text_field($v)),
            'url'          => fn($v) => array('url = ?', esc_url_raw($this->normalizeUrl($v))),
            'username'     => fn($v) => array('username = ?', sanitize_user($v)),
            'redirect_url' => fn($v) => array('redirect_url = ?', esc_url_raw($v)),
            'status'       => fn($v) => array('status = ?', sanitize_key($v)),
            'last_sync'    => fn($v) => array('last_sync = ?', $v),
            'last_error'   => fn($v) => array('last_error = ?', $v),
        );

        foreach ($field_map as $field => $transform) {
            if (isset($data[$field])) {
                $result = $transform($data[$field]);
                $sets[] = $result[0];
                $params[] = $result[1];
            }
        }

        if (isset($data['app_password']) && !empty($data['app_password'])) {
            $sets[] = 'app_password_encrypted = ?';
            $params[] = $this->encrypt($data['app_password']);
        }

        return array('sets' => $sets, 'params' => $params);
    }

    /**
     * Remove an agent site.
     *
     * @param int $id Agent ID.
     * @return bool|WP_Error True on success.
     */
    public function removeAgent($id) {
        $this->file_logger->info('Removing agent site', array('id' => $id));
        
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return new WP_Error('db_error', 'Database not available');
            }
            
            // Actions are deleted via CASCADE
            $stmt = $pdo->prepare("DELETE FROM agent_sites WHERE id = ?");
            $stmt->execute(array((int) $id));
            
            $this->file_logger->info('Agent site removed', array('id' => $id));
            return true;
            
        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to remove agent site');
            return new WP_Error('db_error', 'Failed to remove agent site: ' . $e->getMessage());
        }
    }

    /**
     * Get an agent site by ID.
     *
     * @param int  $id              Agent ID.
     * @param bool $include_password Whether to include decrypted password.
     * @return array|null Agent data or null.
     */
    public function getAgent($id, $include_password = false) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return null;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM agent_sites WHERE id = ?");
            $stmt->execute(array((int) $id));
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($agent && $include_password) {
                $agent['app_password'] = $this->decrypt($agent['app_password_encrypted']);
            }
            
            // Never expose encrypted password directly
            if ($agent) {
                unset($agent['app_password_encrypted']);
            }
            
            return $agent;
            
        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to get agent site');
            return null;
        }
    }

    /**
     * List all agent sites.
     *
     * @param array $filters Optional filters (status).
     * @param int   $limit   Max results.
     * @param int   $offset  Offset for pagination.
     * @return array Array with 'total' and 'agents'.
     */
    public function listAgents($filters = array(), $limit = 100, $offset = 0) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return array('total' => 0, 'agents' => array());
            }

            $query = $this->buildAgentListQuery($filters);
            $total = $this->countAgents($pdo, $query);
            $agents = $this->fetchAgents($pdo, $query, $limit, $offset);

            return array('total' => $total, 'agents' => $agents);

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to list agent sites');

            return array('total' => 0, 'agents' => array());
        }
    }

    /**
     * Build WHERE clause and params for agent listing.
     *
     * @param array $filters Filter options.
     * @return array{where_sql: string, params: array} Query components.
     */
    private function buildAgentListQuery(array $filters): array {
        $where = array();
        $params = array();

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        return array(
            'where_sql' => !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '',
            'params'    => $params,
        );
    }

    /**
     * Count agents matching the query.
     *
     * @param PDO   $pdo   Database connection.
     * @param array $query Query components.
     * @return int Total count.
     */
    private function countAgents(PDO $pdo, array $query): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM agent_sites {$query['where_sql']}");
        $stmt->execute($query['params']);

        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Fetch agent records matching the query.
     *
     * @param PDO   $pdo    Database connection.
     * @param array $query  Query components.
     * @param int   $limit  Max results.
     * @param int   $offset Pagination offset.
     * @return array Agent records.
     */
    private function fetchAgents(PDO $pdo, array $query, int $limit, int $offset): array {
        $params = array_merge($query['params'], array((int) $limit, (int) $offset));
        $sql = "SELECT id, name, url, username, redirect_url, status, last_sync, last_error, created_at, updated_at 
                FROM agent_sites {$query['where_sql']} 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // REMOTE OPERATIONS
    // =========================================================================

    /**
     * Normalize a WordPress URL.
     *
     * @param string $url The URL to normalize.
     * @return string Normalized URL.
     */
    private function normalizeUrl($url) {
        $url = rtrim($url, '/');
        
        // Remove common suffixes
        $suffixes = array('/wp-admin', '/wp-login.php', '/wp-json', '/xmlrpc.php');
        foreach ($suffixes as $suffix) {
            if (substr($url, -strlen($suffix)) === $suffix) {
                $url = substr($url, 0, -strlen($suffix));
            }
        }
        
        // Ensure HTTPS
        if (strpos($url, 'http://') === 0) {
            $url = 'https://' . substr($url, 7);
        }
        
        return $url;
    }

    /**
     * Build authorization header for an agent.
     *
     * @param array $agent Agent data with app_password.
     * @return string Authorization header value.
     */
    private function buildAuthHeader($agent) {
        $credentials = $agent['username'] . ':' . $agent['app_password'];
        return 'Basic ' . base64_encode($credentials);
    }

    /**
     * Make an API request to an agent site.
     *
     * @param int    $agent_id Agent ID.
     * @param string $method   HTTP method.
     * @param string $endpoint API endpoint (relative to /wp-json/).
     * @param array  $body     Request body (for POST/PUT).
     * @return array|WP_Error Response data or error.
     */
    public function apiRequest($agent_id, $method, $endpoint, $body = array()) {
        $agent = $this->getAgent($agent_id, true);
        if (!$agent) {
            return new WP_Error('not_found', 'Agent site not found');
        }

        $url = $this->resolveAgentBaseUrl($agent, $endpoint);
        $args = $this->buildAgentRequestArgs($agent, $method, $body);

        $this->file_logger->debug('Agent API request', array(
            'agent_id' => $agent_id, 'method' => $method, 'url' => $url,
        ));

        $response = wp_remote_request($url, $args);

        return $this->parseAgentResponse($response, $agent_id);
    }

    /**
     * Resolve the full API URL for an agent request.
     *
     * @param array  $agent    Agent data.
     * @param string $endpoint API endpoint.
     * @return string Full URL.
     */
    private function resolveAgentBaseUrl(array $agent, string $endpoint): string {
        $base_url = $agent['url'];
        if (!empty($agent['redirect_url'])) {
            $resolved = $this->resolveRedirectUrl($agent);
            if (!is_wp_error($resolved)) {
                $base_url = $resolved;
            }
        }

        return trailingslashit($base_url) . 'wp-json/' . ltrim($endpoint, '/');
    }

    /**
     * Build request arguments for an agent API call.
     *
     * @param array  $agent  Agent data with app_password.
     * @param string $method HTTP method.
     * @param array  $body   Request body.
     * @return array WP HTTP API args.
     */
    private function buildAgentRequestArgs(array $agent, string $method, array $body): array {
        $args = array(
            'method'    => strtoupper($method),
            'timeout'   => 30,
            'headers'   => array(
                'Authorization' => $this->buildAuthHeader($agent),
                'Content-Type'  => 'application/json',
            ),
            'sslverify' => true,
        );

        if (!empty($body) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($body);
        }

        return $args;
    }

    /**
     * Parse the HTTP response from an agent API call.
     *
     * @param array|WP_Error $response  HTTP response or error.
     * @param int            $agent_id  Agent ID for logging.
     * @return array|WP_Error Parsed data or error.
     */
    private function parseAgentResponse($response, int $agent_id) {
        if (is_wp_error($response)) {
            $this->logAction($agent_id, 'api_error', null, 'failed', null, $response->get_error_message());

            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_json = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 400) {
            $error_msg = isset($body_json['error']['message']) ? $body_json['error']['message'] : "HTTP {$status_code}";

            return new WP_Error('api_error', $error_msg, array('status' => $status_code, 'response' => $body_json));
        }

        return $body_json;
    }

    /**
     * Resolve a redirect URL for an agent.
     *
     * @param array $agent Agent data.
     * @return string|WP_Error Resolved URL or error.
     */
    private function resolveRedirectUrl($agent) {
        if ($this->isRedirectCacheValid($agent)) {
            return $agent['redirect_resolved'];
        }

        $resolved = $this->followRedirectChain($agent['redirect_url']);
        if (is_wp_error($resolved)) {
            return $resolved;
        }

        $this->updateAgent($agent['id'], array(
            'redirect_resolved'    => $resolved,
            'redirect_resolved_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ));

        return $resolved;
    }

    /**
     * Check if the cached redirect URL is still valid.
     *
     * @param array $agent Agent data.
     * @return bool True if cache is valid.
     */
    private function isRedirectCacheValid(array $agent): bool {
        if (empty($agent['redirect_resolved']) || empty($agent['redirect_resolved_at'])) {
            return false;
        }

        $resolved_at = strtotime($agent['redirect_resolved_at']);
        $cache_days = UPDATE_CACHE_DAYS_DEFAULT;

        return (time() < $resolved_at + ($cache_days * DAY_IN_SECONDS));
    }

    /**
     * Follow a redirect chain to find the final URL.
     *
     * @param string $url           Starting URL.
     * @param int    $maxRedirects  Maximum redirects to follow.
     * @return string|WP_Error Final URL or error.
     */
    private function followRedirectChain(string $url, int $maxRedirects = 5) {
        for ($i = 0; $i < $maxRedirects; $i++) {
            $response = wp_remote_head($url, array(
                'timeout' => 15, 'redirection' => 0, 'sslverify' => true,
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            if (!in_array($status, array(301, 302, 303, 307, 308))) {
                break;
            }

            $location = wp_remote_retrieve_header($response, 'location');
            if (!empty($location)) {
                $url = $location;
            }
        }

        return $url;
    }

    /**
     * Test connection to an agent site.
     *
     * @param int $agent_id Agent ID.
     * @return array Test result.
     */
    public function testConnection($agent_id) {
        $this->file_logger->info('Testing agent connection', array('id' => $agent_id));
        
        $result = $this->apiRequest($agent_id, 'GET', API_FULL_NAMESPACE . '/status');
        
        if (is_wp_error($result)) {
            $this->updateAgent($agent_id, array(
                'status'     => 'error',
                'last_error' => $result->get_error_message(),
            ));
            
            $this->logAction($agent_id, ACTION_AGENT_TEST, null, STATUS_FAILED, null, $result->get_error_message());
            
            return array(
                'success' => false,
                'message' => $result->get_error_message(),
            );
        }
        
        $this->updateAgent($agent_id, array(
            'status'     => 'connected',
            'last_sync'  => gmdate('Y-m-d\TH:i:s\Z'),
            'last_error' => null,
        ));
        
        $this->logAction($agent_id, ACTION_AGENT_TEST, null, STATUS_SUCCESS);
        
        return array(
            'success' => true,
            'message' => 'Connection successful',
            'data'    => $result,
        );
    }

    /**
     * Sync plugins from an agent site.
     *
     * @param int $agent_id Agent ID.
     * @return array|WP_Error Plugin list or error.
     */
    public function syncPlugins($agent_id) {
        $this->file_logger->info('Syncing plugins from agent', array('id' => $agent_id));
        
        $result = $this->apiRequest($agent_id, 'GET', API_FULL_NAMESPACE . '/plugins');
        
        if (is_wp_error($result)) {
            $this->logAction($agent_id, ACTION_AGENT_SYNC, null, STATUS_FAILED, null, $result->get_error_message());
            return $result;
        }
        
        $this->updateAgent($agent_id, array(
            'status'    => 'connected',
            'last_sync' => gmdate('Y-m-d\TH:i:s\Z'),
        ));
        
        $plugins = isset($result['plugins']) ? $result['plugins'] : $result;
        $this->logAction($agent_id, ACTION_AGENT_SYNC, null, STATUS_SUCCESS, array('count' => count($plugins)));
        
        return $plugins;
    }

    /**
     * Execute an action on a plugin at an agent site.
     *
     * @param int    $agent_id Agent ID.
     * @param string $action   Action: enable, disable, delete.
     * @param string $slug     Plugin slug.
     * @return array|WP_Error Result or error.
     */
    public function executePluginAction($agent_id, $action, $slug) {
        $this->file_logger->info('Executing plugin action on agent', array(
            'agent_id' => $agent_id,
            'action'   => $action,
            'slug'     => $slug,
        ));
        
        $endpoint = API_FULL_NAMESPACE . '/plugins/' . urlencode($slug) . '/' . $action;
        $result = $this->apiRequest($agent_id, 'POST', $endpoint);
        
        if (is_wp_error($result)) {
            $this->logAction($agent_id, 'plugin_' . $action, $slug, STATUS_FAILED, null, $result->get_error_message());
            return $result;
        }
        
        $this->logAction($agent_id, 'plugin_' . $action, $slug, STATUS_SUCCESS);
        
        return array(
            'success' => true,
            'message' => ucfirst($action) . ' executed successfully',
            'data'    => $result,
        );
    }

    // =========================================================================
    // ACTION LOGGING
    // =========================================================================

    /**
     * Log an agent action.
     *
     * @param int         $agent_id    Agent ID.
     * @param string      $action      Action type.
     * @param string|null $plugin      Target plugin slug.
     * @param string      $status      Status (success/failed).
     * @param array|null  $details     Additional details.
     * @param string|null $error_msg   Error message if failed.
     * @return int|false Insert ID or false.
     */
    public function logAction($agent_id, $action, $plugin = null, $status = 'success', $details = null, $error_msg = null) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return false;
            }
            
            $stmt = $pdo->prepare("INSERT INTO agent_actions 
                (agent_site_id, action, target_plugin, status, details, error_msg, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute(array(
                (int) $agent_id,
                $action,
                $plugin,
                $status,
                $details ? json_encode($details) : null,
                $error_msg,
                gmdate('Y-m-d\TH:i:s\Z'),
            ));
            
            return (int) $pdo->lastInsertId();
            
        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to log agent action');
            return false;
        }
    }

    /**
     * Get action history for an agent.
     *
     * @param int $agent_id Agent ID.
     * @param int $limit    Max results.
     * @param int $offset   Offset.
     * @return array Array with 'total' and 'actions'.
     */
    public function getActionHistory($agent_id, $limit = 50, $offset = 0) {
        try {
            $pdo = $this->db->get_pdo();
            if (!$pdo) {
                return array('total' => 0, 'actions' => array());
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM agent_actions WHERE agent_site_id = ?");
            $stmt->execute(array((int) $agent_id));
            $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $pdo->prepare("SELECT * FROM agent_actions 
                WHERE agent_site_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?");
            $stmt->execute(array((int) $agent_id, (int) $limit, (int) $offset));
            $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->decodeActionDetails($actions);

            return array('total' => $total, 'actions' => $actions);

        } catch (PDOException $e) {
            $this->file_logger->log_exception($e, 'Failed to get action history');

            return array('total' => 0, 'actions' => array());
        }
    }

    /**
     * Decode JSON details in action records.
     *
     * @param array &$actions Action records reference.
     */
    private function decodeActionDetails(array &$actions) {
        foreach ($actions as &$action) {
            if (!empty($action['details'])) {
                $action['details'] = json_decode($action['details'], true);
            }
        }
    }
}

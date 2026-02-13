<?php
/**
 * AgentHandlerTrait — REST handlers for agent site management.
 *
 * Extracted from riseup-asia-uploader.php (lines 4306–4530).
 *
 * @package RiseupAsiaUploader
 */

trait AgentHandlerTrait {

    /**
     * Handle listing all agent sites.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_list_agents($request) {
        return $this->safe_execute(function() use ($request) {
            $this->file_logger->info('Listing agent sites');

            $status = $request->get_param('status');
            $limit = $request->get_param('limit') ?: 100;
            $offset = $request->get_param('offset') ?: 0;

            $filters = array();
            if ($status) {
                $filters['status'] = sanitize_key($status);
            }

            $manager = RiseupAgentManager::getInstance();
            $result = $manager->listAgents($filters, $limit, $offset);

            return new WP_REST_Response(array(
                'success' => true,
                'total'   => $result['total'],
                'agents'  => $result['agents'],
            ), 200);
        }, 'list_agents');
    }

    /**
     * Handle adding a new agent site.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_add_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $this->file_logger->info('Adding agent site');

            $data = array(
                'name'         => $request->get_param('name'),
                'url'          => $request->get_param('url'),
                'username'     => $request->get_param('username'),
                'app_password' => $request->get_param('app_password'),
                'redirect_url' => $request->get_param('redirect_url'),
            );

            $manager = RiseupAgentManager::getInstance();
            $result = $manager->addAgent($data);

            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 400);
            }

            return new WP_REST_Response(array(
                'success'  => true,
                'agent_id' => $result,
                'message'  => 'Agent site added successfully',
            ), 201);
        }, 'add_agent');
    }

    /**
     * Handle getting a single agent site.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_get_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->file_logger->info('Getting agent site', array('id' => $id));

            $manager = RiseupAgentManager::getInstance();
            $agent = $manager->getAgent($id, false);

            if (!$agent) {
                return $this->error_response('Agent site not found', 404);
            }

            return new WP_REST_Response(array(
                'success' => true,
                'agent'   => $agent,
            ), 200);
        }, 'get_agent');
    }

    /**
     * Handle removing an agent site.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_remove_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->file_logger->info('Removing agent site', array('id' => $id));

            $manager = RiseupAgentManager::getInstance();
            $result = $manager->removeAgent($id);

            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 400);
            }

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Agent site removed successfully',
            ), 200);
        }, 'remove_agent');
    }

    /**
     * Handle testing agent connection.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_test_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->file_logger->info('Testing agent connection', array('id' => $id));

            $manager = RiseupAgentManager::getInstance();
            $result = $manager->testConnection($id);

            $status_code = $result['success'] ? 200 : 400;
            return new WP_REST_Response($result, $status_code);
        }, 'test_agent');
    }

    /**
     * Handle syncing plugins from agent.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_sync_agent($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->file_logger->info('Syncing plugins from agent', array('id' => $id));

            $manager = RiseupAgentManager::getInstance();
            $result = $manager->syncPlugins($id);

            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 400);
            }

            return new WP_REST_Response(array(
                'success' => true,
                'plugins' => $result,
                'count'   => count($result),
            ), 200);
        }, 'sync_agent');
    }

    /**
     * Handle executing action on agent plugin.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_agent_action($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $action = sanitize_key($request->get_param('action'));
            $slug = sanitize_text_field($request->get_param('slug'));

            $this->file_logger->info('Executing agent action', array(
                'id'     => $id,
                'action' => $action,
                'slug'   => $slug,
            ));

            $allowed_actions = array('enable', 'disable', 'delete');
            if (!in_array($action, $allowed_actions)) {
                return $this->error_response('Invalid action. Allowed: ' . implode(', ', $allowed_actions), 400);
            }

            if (empty($slug)) {
                return $this->error_response('Plugin slug is required', 400);
            }

            $manager = RiseupAgentManager::getInstance();
            $result = $manager->executePluginAction($id, $action, $slug);

            if (is_wp_error($result)) {
                return $this->error_response($result->get_error_message(), 400);
            }

            return new WP_REST_Response($result, 200);
        }, 'agent_action');
    }

    /**
     * Handle getting agent action history.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function handle_agent_history($request) {
        return $this->safe_execute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $limit = $request->get_param('limit') ?: 50;
            $offset = $request->get_param('offset') ?: 0;

            $this->file_logger->info('Getting agent action history', array('id' => $id));

            $manager = RiseupAgentManager::getInstance();
            $result = $manager->getActionHistory($id, $limit, $offset);

            return new WP_REST_Response(array(
                'success' => true,
                'total'   => $result['total'],
                'actions' => $result['actions'],
            ), 200);
        }, 'agent_history');
    }
}

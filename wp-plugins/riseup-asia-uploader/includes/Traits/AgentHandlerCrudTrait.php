<?php
/**
 * AgentHandlerCrudTrait — REST handlers for agent CRUD operations.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait AgentHandlerCrudTrait {

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
}

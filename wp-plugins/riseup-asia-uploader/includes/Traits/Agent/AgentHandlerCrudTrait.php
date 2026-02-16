<?php
/**
 * AgentHandlerCrudTrait — REST handlers for agent CRUD operations.
 *
 * @package RiseupAsia\Traits\Agent
 * @since   2.0.0
 */

namespace RiseupAsia\Traits\Agent;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

trait AgentHandlerCrudTrait {

    /** Handle listing all agent sites. */
    public function handleListAgents(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $this->fileLogger->info('Listing agent sites');
            $status = $request->get_param('status');
            $limit = $request->get_param('limit') ?: 100;
            $offset = $request->get_param('offset') ?: 0;
            $filters = array();
            if ($status) { $filters['status'] = sanitize_key($status); }
            $manager = \RiseupAgentManager::getInstance();
            $result = $manager->listAgents($filters, $limit, $offset);
            return new WP_REST_Response(array('success' => true, 'total' => $result['total'], 'agents' => $result['agents']), 200);
        }, 'list_agents');
    }

    /** Handle adding a new agent site. */
    public function handleAddAgent(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $this->fileLogger->info('Adding agent site');
            $data = $this->extractAgentData($request);
            $manager = \RiseupAgentManager::getInstance();
            $result = $manager->addAgent($data);
            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), 400);
            }
            return new WP_REST_Response(array('success' => true, 'agent_id' => $result, 'message' => 'Agent site added successfully'), 201);
        }, 'add_agent');
    }

    /** Extract agent data from a REST request. */
    private function extractAgentData(WP_REST_Request $request): array {
        return array(
            'name' => $request->get_param('name'), 'url' => $request->get_param('url'),
            'username' => $request->get_param('username'), 'app_password' => $request->get_param('app_password'),
            'redirect_url' => $request->get_param('redirect_url'),
        );
    }

    /** Handle getting a single agent site. */
    public function handleGetAgent(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->fileLogger->info('Getting agent site', array('id' => $id));
            $manager = \RiseupAgentManager::getInstance();
            $agent = $manager->getAgent($id, false);
            if (!$agent) {
                return $this->errorResponse('Agent site not found', 404);
            }
            return new WP_REST_Response(array('success' => true, 'agent' => $agent), 200);
        }, 'get_agent');
    }

    /** Handle removing an agent site. */
    public function handleRemoveAgent(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->fileLogger->info('Removing agent site', array('id' => $id));
            $manager = \RiseupAgentManager::getInstance();
            $result = $manager->removeAgent($id);
            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), 400);
            }
            return new WP_REST_Response(array('success' => true, 'message' => 'Agent site removed successfully'), 200);
        }, 'remove_agent');
    }
}
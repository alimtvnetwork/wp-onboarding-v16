<?php
/**
 * AgentHandlerActionTrait — REST handlers for agent test, sync, action, and history.
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
use Throwable;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Agent\AgentManager;

trait AgentHandlerActionTrait {

    /** Handle testing agent connection. */
    public function handleTestAgent(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->fileLogger->info('Testing agent connection', array('id' => $id));
            $manager = AgentManager::getInstance();
            $result = $manager->testConnection($id);
            $status_code = $result['success'] ? HttpStatusType::Ok->value : HttpStatusType::BadRequest->value;

            return new WP_REST_Response($result, $status_code);
        }, 'test_agent');
    }

    /** Handle syncing plugins from agent. */
    public function handleSyncAgent(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->fileLogger->info('Syncing plugins from agent', array('id' => $id));
            $manager = AgentManager::getInstance();
            $result = $manager->syncPlugins($id);
            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), HttpStatusType::BadRequest->value);
            }

            return new WP_REST_Response(array('success' => true, 'plugins' => $result, 'count' => count($result)), HttpStatusType::Ok->value);
        }, 'sync_agent');
    }

    /** Handle executing action on agent plugin. */
    public function handleAgentAction(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $action = sanitize_key($request->get_param('action'));
            $slug = sanitize_text_field($request->get_param('slug'));
            $this->fileLogger->info('Executing agent action', array('id' => $id, 'action' => $action, 'slug' => $slug));
            $allowed_actions = array(ActionType::Enable->value, ActionType::Disable->value, ActionType::Delete->value);
            if (BooleanHelpers::isAbsentFromList($action, $allowed_actions)) {
                return $this->errorResponse('Invalid action. Allowed: ' . implode(', ', $allowed_actions), HttpStatusType::BadRequest->value);
            }
            if (empty($slug)) {
                return $this->errorResponse('Plugin slug is required', HttpStatusType::BadRequest->value);
            }
            $manager = AgentManager::getInstance();
            $result = $manager->executePluginAction($id, $action, $slug);
            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), HttpStatusType::BadRequest->value);
            }

            return new WP_REST_Response($result, HttpStatusType::Ok->value);
        }, 'agent_action');
    }

    /** Handle getting agent action history. */
    public function handleAgentHistory(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $limit = $request->get_param('limit') ?: 50;
            $offset = $request->get_param('offset') ?: 0;
            $this->fileLogger->info('Getting agent action history', array('id' => $id));
            $manager = AgentManager::getInstance();
            $result = $manager->getActionHistory($id, $limit, $offset);

            return new WP_REST_Response(array('success' => true, 'total' => $result['total'], 'actions' => $result['actions']), HttpStatusType::Ok->value);
        }, 'agent_history');
    }
}

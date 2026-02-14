<?php
/**
 * AgentHandlerActionTrait — REST handlers for agent test, sync, action, and history.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AgentHandlerActionTrait {

    /** Handle testing agent connection. */
    public function handleTestAgent($request) {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->fileLogger->info('Testing agent connection', array('id' => $id));
            $manager = RiseupAgentManager::getInstance();
            $result = $manager->testConnection($id);
            $status_code = $result['success'] ? 200 : 400;
            return new WP_REST_Response($result, $status_code);
        }, 'test_agent');
    }

    /** Handle syncing plugins from agent. */
    public function handleSyncAgent($request) {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->fileLogger->info('Syncing plugins from agent', array('id' => $id));
            $manager = RiseupAgentManager::getInstance();
            $result = $manager->syncPlugins($id);
            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), 400);
            }
            return new WP_REST_Response(array('success' => true, 'plugins' => $result, 'count' => count($result)), 200);
        }, 'sync_agent');
    }

    /** Handle executing action on agent plugin. */
    public function handleAgentAction($request) {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $action = sanitize_key($request->get_param('action'));
            $slug = sanitize_text_field($request->get_param('slug'));
            $this->fileLogger->info('Executing agent action', array('id' => $id, 'action' => $action, 'slug' => $slug));
            $allowed_actions = array('enable', 'disable', 'delete');
            if (RiseupBooleanHelpers::isNotInList($action, $allowed_actions)) {
                return $this->errorResponse('Invalid action. Allowed: ' . implode(', ', $allowed_actions), 400);
            }
            if (empty($slug)) {
                return $this->errorResponse('Plugin slug is required', 400);
            }
            $manager = RiseupAgentManager::getInstance();
            $result = $manager->executePluginAction($id, $action, $slug);
            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), 400);
            }
            return new WP_REST_Response($result, 200);
        }, 'agent_action');
    }

    /** Handle getting agent action history. */
    public function handleAgentHistory($request) {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $limit = $request->get_param('limit') ?: 50;
            $offset = $request->get_param('offset') ?: 0;
            $this->fileLogger->info('Getting agent action history', array('id' => $id));
            $manager = RiseupAgentManager::getInstance();
            $result = $manager->getActionHistory($id, $limit, $offset);
            return new WP_REST_Response(array('success' => true, 'total' => $result['total'], 'actions' => $result['actions']), 200);
        }, 'agent_history');
    }
}

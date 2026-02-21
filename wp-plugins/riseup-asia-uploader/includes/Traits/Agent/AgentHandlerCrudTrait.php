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
use RiseupAsia\Enums\AgentFieldType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Agent\AgentManager;

trait AgentHandlerCrudTrait {

    /** Handle listing all agent sites. */
    public function handleListAgents(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $this->fileLogger->info('Listing agent sites');
            $status = $request->get_param(AgentFieldType::Status->value);
            $limit = $request->get_param('limit') ?: 100;
            $offset = $request->get_param('offset') ?: 0;
            $filters = array();
            if ($status) { $filters[AgentFieldType::Status->value] = sanitize_key($status); }
            $manager = AgentManager::getInstance();
            $result = $manager->listAgents($filters, $limit, $offset);

            return new WP_REST_Response(array(ResponseKeyType::Success->value => true, ResponseKeyType::Total->value => $result[ResponseKeyType::Total->value], ResponseKeyType::Agents->value => $result[ResponseKeyType::Agents->value]), HttpStatusType::Ok->value);
        }, 'list_agents');
    }

    /** Handle adding a new agent site. */
    public function handleAddAgent(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $this->fileLogger->info('Adding agent site');
            $data = $this->extractAgentData($request);
            $manager = AgentManager::getInstance();
            $result = $manager->addAgent($data);
            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), HttpStatusType::BadRequest->value);
            }

            return new WP_REST_Response(array(ResponseKeyType::Success->value => true, 'agent_id' => $result, ResponseKeyType::Message->value => 'Agent site added successfully'), HttpStatusType::Created->value);
        }, 'add_agent');
    }

    /** Extract agent data from a REST request. */
    private function extractAgentData(WP_REST_Request $request): array {
        return array(
            AgentFieldType::Name->value        => $request->get_param(AgentFieldType::Name->value),
            AgentFieldType::Url->value         => $request->get_param(AgentFieldType::Url->value),
            AgentFieldType::Username->value    => $request->get_param(AgentFieldType::Username->value),
            AgentFieldType::AppPassword->value => $request->get_param(AgentFieldType::AppPassword->value),
            AgentFieldType::RedirectUrl->value => $request->get_param(AgentFieldType::RedirectUrl->value),
        );
    }

    /** Handle getting a single agent site. */
    public function handleGetAgent(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->fileLogger->info('Getting agent site', array('id' => $id));
            $manager = AgentManager::getInstance();
            $agent = $manager->getAgent($id, false);
            $isAgentMissing = ($agent === null);
            if ($isAgentMissing) {
                return $this->errorResponse('Agent site not found', HttpStatusType::NotFound->value);
            }

            return new WP_REST_Response(array(ResponseKeyType::Success->value => true, 'agent' => $agent), HttpStatusType::Ok->value);
        }, 'get_agent');
    }

    /** Handle removing an agent site. */
    public function handleRemoveAgent(WP_REST_Request $request): WP_REST_Response {
        return $this->safeExecute(function() use ($request) {
            $id = (int) $request->get_param('id');
            $this->fileLogger->info('Removing agent site', array('id' => $id));
            $manager = AgentManager::getInstance();
            $result = $manager->removeAgent($id);
            if (is_wp_error($result)) {
                return $this->errorResponse($result->get_error_message(), HttpStatusType::BadRequest->value);
            }

            return new WP_REST_Response(array(ResponseKeyType::Success->value => true, ResponseKeyType::Message->value => 'Agent site removed successfully'), HttpStatusType::Ok->value);
        }, 'remove_agent');
    }
}

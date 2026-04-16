<?php
/**
 * MachineApprovalTrait — Remote machine approval via REST API.
 *
 * PUT /machines/approve: Adds a machine name to the approved_machines list
 * stored in the WordPress option, without requiring plugin redeployment.
 *
 * @package RiseupAsia\Traits\Machine
 * @since   2.17.0
 */

namespace RiseupAsia\Traits\Machine;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;

trait MachineApprovalTrait
{
    /**
     * Handle PUT /machines/approve — add a machine to the approved list.
     *
     * Expects JSON body: { "machine": "MACHINE-NAME" }
     * Stores in WP option: {settings_group}.approved_machines[]
     */
    public function handleApproveMachine(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function () use ($request) {
            $body = $this->extractValidBody($request);
            $isBodyInvalid = ($body === null);

            if ($isBodyInvalid) {
                return $this->validationError('Invalid or missing JSON body', $request);
            }

            $machineName = trim($body['machine'] ?? '');
            $isMachineEmpty = ($machineName === '');

            if ($isMachineEmpty) {
                return new WP_REST_Response(
                    [
                        ResponseKeyType::Success->value => false,
                        ResponseKeyType::Error->value   => 'Machine name is required in request body',
                        'code'                          => 'machine_name_missing',
                    ],
                    HttpStatusType::BadRequest->value,
                );
            }

            $settingsKey = PluginConfigType::SettingsGroup->value;
            $settings = get_option($settingsKey, []);
            $approvedMachines = $settings['approved_machines'] ?? [];

            // Check if already approved (case-insensitive)
            $lowerMachine = strtolower($machineName);
            $isAlreadyApproved = false;

            foreach ($approvedMachines as $existing) {
                if (strtolower($existing) === $lowerMachine) {
                    $isAlreadyApproved = true;
                    break;
                }
            }

            if ($isAlreadyApproved) {
                return new WP_REST_Response(
                    [
                        ResponseKeyType::Success->value => true,
                        ResponseKeyType::Message->value => "Machine '$machineName' is already approved",
                        'machine'                       => $machineName,
                        'approved_machines'              => $approvedMachines,
                        'already_approved'               => true,
                    ],
                    HttpStatusType::Ok->value,
                );
            }

            $approvedMachines[] = $machineName;
            $settings['approved_machines'] = $approvedMachines;
            update_option($settingsKey, $settings);

            $this->fileLogger->info('Machine approved remotely', [
                'machine'           => $machineName,
                'approved_machines' => $approvedMachines,
            ]);

            return new WP_REST_Response(
                [
                    ResponseKeyType::Success->value => true,
                    ResponseKeyType::Message->value => "Machine '$machineName' approved successfully",
                    'machine'                       => $machineName,
                    'approved_machines'              => $approvedMachines,
                ],
                HttpStatusType::Ok->value,
            );
        }, 'handleApproveMachine');
    }
}

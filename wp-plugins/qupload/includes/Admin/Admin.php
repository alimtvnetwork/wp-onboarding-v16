<?php
/**
 * Admin — QUpload WordPress admin pages and AJAX handlers.
 *
 * @package QUpload\Admin
 * @since   2.1.0
 */

namespace QUpload\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Admin\Traits\AdminMenuTrait;
use QUpload\Admin\Traits\AdminErrorAjaxTrait;
use QUpload\Enums\AjaxActionType;
use QUpload\Enums\HookType;
use QUpload\Helpers\ErrorLogHelper;

class Admin {
    use AdminMenuTrait;
    use AdminErrorAjaxTrait;

    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action(HookType::AdminMenu->value, [$this, 'addAdminMenu']);
        add_action(HookType::AdminEnqueue->value, [$this, 'enqueueAdminAssets']);
        add_action(HookType::ajax(AjaxActionType::ReadLogFile->value), [$this, 'ajaxReadLogFile']);
        add_action(HookType::ajax(AjaxActionType::ClearLogFile->value), [$this, 'ajaxClearLogFile']);
    }
}

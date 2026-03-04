<?php
/**
 * Shared Partial: Modal Styles
 *
 * Base CSS for the `.riseup-modal` component used across all admin pages.
 * Include this once per page that renders modals (typically in the page's styles partial).
 *
 * @package RiseupAsiaUploader
 * @since   2.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
/* ================================================================
   SHARED MODAL STYLES
   ================================================================ */
.riseup-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.riseup-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
}
.riseup-modal-content {
    position: relative;
    background: #fff;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    border-radius: 4px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.riseup-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.riseup-modal-header .modal-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
}
.riseup-modal-header h3 {
    margin: 0;
}
.riseup-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    line-height: 1;
    padding: 0 4px;
}
.riseup-modal-close:hover {
    color: #dc3232;
}
.riseup-modal-body {
    padding: 20px;
    overflow-y: auto;
}
.riseup-modal-footer {
    padding: 12px 20px;
    border-top: 1px solid #ddd;
}
.riseup-modal-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}
.riseup-warning-text {
    color: #d63638;
    font-weight: 500;
}
</style>

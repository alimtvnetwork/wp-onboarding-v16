<?php
/**
 * Business Profile Page - Styles
 * 
 * @package Category_Generator_Area
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<style>
.cg-profile-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: <?php echo CG_Constants::SPACING_LARGE; ?>px;
}

@media (max-width: <?php echo CG_Constants::BREAKPOINT_TABLET; ?>px) {
    .cg-profile-grid {
        grid-template-columns: 1fr;
    }
}

.<?php echo CG_CSS::FORM_ROW; ?> {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: <?php echo CG_Constants::SPACING_MEDIUM; ?>px;
}

.<?php echo CG_CSS::FORM_GROUP; ?> {
    margin-bottom: <?php echo CG_Constants::SPACING_MEDIUM; ?>px;
}

.<?php echo CG_CSS::FORM_GROUP; ?> label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #1d2327;
}

.<?php echo CG_CSS::FORM_GROUP; ?> input,
.<?php echo CG_CSS::FORM_GROUP; ?> textarea,
.<?php echo CG_CSS::FORM_GROUP; ?> select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    font-size: 14px;
}

.<?php echo CG_CSS::FORM_GROUP; ?> input:focus,
.<?php echo CG_CSS::FORM_GROUP; ?> textarea:focus,
.<?php echo CG_CSS::FORM_GROUP; ?> select:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}

.<?php echo CG_CSS::TEXT_HINT; ?> {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #646970;
}

.cg-save-notice {
    position: fixed;
    top: 50px;
    right: <?php echo CG_Constants::SPACING_LARGE; ?>px;
    background: #00a32a;
    color: white;
    padding: 12px 24px;
    border-radius: 4px;
    z-index: <?php echo CG_Constants::Z_INDEX_NOTICE; ?>;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>

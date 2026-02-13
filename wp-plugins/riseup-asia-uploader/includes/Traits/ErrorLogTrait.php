<?php
/**
 * ErrorLogTrait — error log and error session retrieval handlers.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsiaUploader
 */

require_once dirname(__FILE__) . '/ErrorLogHandlerTrait.php';
require_once dirname(__FILE__) . '/ErrorSessionHandlerTrait.php';

trait ErrorLogTrait {
    use ErrorLogHandlerTrait;
    use ErrorSessionHandlerTrait;
}

<?php
/**
 * UserCrudTrait — Shell composing user management sub-traits.
 *
 * @package RiseupAsia\Traits\User
 * @since   2.13.0
 */

namespace RiseupAsia\Traits\User;

if (!defined('ABSPATH')) {
    exit;
}

trait UserCrudTrait {
    use UserFieldMapperTrait;
    use UserSocialTrait;
    use UserYoastTrait;
    use UserReadTrait;
    use UserWriteTrait;
    use UserDeleteTrait;
    use UserAppPasswordTrait;
    use UserExportCsvTrait;
    use UserImportCsvTrait;
    use UserExportSqliteTrait;
    use UserImportSqliteTrait;
}

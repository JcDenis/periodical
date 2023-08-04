<?php
/**
 * @brief periodical, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugin
 *
 * @author Jean-Christian Denis and contributors
 *
 * @copyright Jean-Christian Denis
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('DC_RC_PATH')) {
    return null;
}

$this->registerModule(
    'Periodical',
    'Published periodically entries',
    'Jean-Christian Denis and contributors',
    '2023.08.04',
    [
        'requires' => [
            ['php', '8.1'],
            ['core', '2.27'],
        ],
        'permissions' => dcCore::app()->auth->makePermissions([
            dcCore::app()->auth::PERMISSION_USAGE,
            dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
        ]),
        'type'       => 'plugin',
        'support'    => 'http://gitea.jcdenis.fr/Dotclear/periodical',
        'details'    => 'http://gitea.jcdenis.fr/Dotclear/periodical/src/branch/master/README.md',
        'repository' => 'http://gitea.jcdenis.fr/Dotclear/periodical/raw/branch/master/dcstore.xml',
        'settings'   => [
            'blog' => '#params.periodical_params',
        ],
    ]
);

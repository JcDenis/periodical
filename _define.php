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
if (!defined('DC_RC_PATH') || is_null(dcCore::app()->auth)) {
    return null;
}

$this->registerModule(
    'Periodical',
    'Published periodically entries',
    'Jean-Christian Denis and contributors',
    '2023.05.13',
    [
        'requires' => [
            ['php', '8.1'],
            ['core', '2.26'],
        ],
        'permissions' => dcCore::app()->auth->makePermissions([
            dcCore::app()->auth::PERMISSION_USAGE,
            dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
        ]),
        'type'       => 'plugin',
        'support'    => 'https://github.com/JcDenis/periodical',
        'details'    => 'https://plugins.dotaddict.org/dc2/details/periodical',
        'repository' => 'https://raw.githubusercontent.com/JcDenis/periodical/master/dcstore.xml',
        'settings'   => [
            'blog' => '#params.periodical_params',
        ],
    ]
);

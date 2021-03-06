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
    '2022.05.16',
    [
        'requires'    => [['core', '2.22']],
        'permissions' => 'usage,contentadmin',
        'type'        => 'plugin',
        'support'     => 'https://github.com/JcDenis/periodical',
        'details'     => 'https://plugins.dotaddict.org/dc2/details/periodical',
        'repository'  => 'https://raw.githubusercontent.com/JcDenis/periodical/master/dcstore.xml',
        'settings'    => [
            'blog' => '#params.periodical_params'
        ]
    ]
);
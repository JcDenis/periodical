<?php
/**
 * @file
 * @brief       The plugin periodical definition
 * @ingroup     periodical
 *
 * @defgroup    periodical Plugin periodical.
 *
 * Published periodically entries.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

$this->registerModule(
    'Periodical',
    'Published periodically entries',
    'Jean-Christian Denis and contributors',
    '2025.03.02',
    [
        'requires'    => [['core', '2.28']],
        'permissions' => 'My',
        'settings'    => ['blog' => '#params.' . $this->id . '_params'],
        'type'        => 'plugin',
        'support'     => 'https://github.com/JcDenis/' . $this->id . '/issues',
        'details'     => 'https://github.com/JcDenis/' . $this->id . '/',
        'repository'  => 'https://raw.githubusercontent.com/JcDenis/' . $this->id . '/master/dcstore.xml',
        'date'        => '2025-02-24T23:31:12+00:00',
    ]
);

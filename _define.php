<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of periodical, a plugin for Dotclear 2.
# 
# Copyright (c) 2009-2021 Jean-Christian Denis and contributors
# 
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_RC_PATH')) {
    return null;
}

$this->registerModule(
    'Periodical',
    'Published periodically entries',
    'Jean-Christian Denis and contributors',
    '2021.08.23',
    [
        'permissions' => 'usage,contentadmin',
        'type' => 'plugin',
        'dc_min' => '2.19',
        'support' => 'https://github.com/JcDenis/periodical',
        'details' => 'https://plugins.dotaddict.org/dc2/details/periodical'
    ]
);
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

# Check Dotclear version
if (!method_exists('dcUtils', 'versionsCompare') 
 || dcUtils::versionsCompare(DC_VERSION, '2.6', '<', false)
) {
	return null;
}

$d = dirname(__FILE__).'/inc/';

# DB class
$__autoload['periodical'] = $d.'class.periodical.php';
# Admin list and pagers
$__autoload['adminPeriodicalList'] = $d.'lib.index.pager.php';

# Add to plugn soCialMe (writer part)
$__autoload['periodicalSoCialMeWriter'] = $d.'lib.periodical.socialmewriter.php';

$core->addBehavior(
	'soCialMeWriterMarker',
	array('periodicalSoCialMeWriter', 'soCialMeWriterMarker')
);
$core->addBehavior(
	'periodicalAfterPublishedPeriodicalEntry',
	array('periodicalSoCialMeWriter', 'periodicalAfterPublishedPeriodicalEntry')
);

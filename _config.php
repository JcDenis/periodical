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

if (!defined('DC_CONTEXT_MODULE')) {
    return null;
}

$redir = empty($_REQUEST['redir']) ? 
    $list->getURL() . '#plugins' : $_REQUEST['redir'];

# -- Combos --
$sortby_combo = [
    __('Create date') => 'post_creadt',
    __('Date') => 'post_dt',
    __('Id') => 'post_id'
];
$order_combo = [
    __('Descending') => 'desc',
    __('Ascending') => 'asc'
];

# -- Get settings --
$core->blog->settings->addNamespace('periodical');
$s = $core->blog->settings->periodical;

$s_active = (boolean) $s->periodical_active;
$s_upddate = (boolean) $s->periodical_upddate;
$s_updurl = (boolean) $s->periodical_updurl;
$e_order = (string) $s->periodical_pub_order;
$e_order = explode(' ', $e_order);
$s_sortby = in_array($e_order[0], $sortby_combo) ? $e_order[0] : 'post_dt';
$s_order = isset($e_order[1]) && strtolower($e_order[1]) == 'desc' ? 'desc' : 'asc';

# -- Set settings --
if (!empty($_POST['save'])) {
    try {
        $s_active = !empty($_POST['s_active']);
        $s_upddate = !empty($_POST['s_upddate']);
        $s_updurl = !empty($_POST['s_updurl']);
        $s_sortby = $_POST['s_sortby'];
        $s_order = $_POST['s_order'];

        $s->put('periodical_active', $s_active);
        $s->put('periodical_upddate', $s_upddate);
        $s->put('periodical_updurl', $s_updurl);
        $s->put('periodical_pub_order', $s_sortby . ' ' . $s_order);

        $core->blog->triggerBlog();

        dcPage::addSuccessNotice(
            __('Configuration has been successfully updated.')
        );
        http::redirect($list->getURL(
            'module=periodical&conf=1&redir=' . $list->getRedir()
        ));
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}

# -- Display form --
echo '

<div class="fieldset">
<h4>' . __('Activation') . '</h4>

<p><label class="classic" for="s_active">' .
form::checkbox('s_active', 1, $s_active) .
__('Enable plugin') . '</label></p>

</div>

<div class="fieldset">
<h4>' . __('Dates of published entries') . '</h4>

<p><label for="s_upddate">' .
form::checkbox('s_upddate', 1, $s_upddate) .
__('Update post date') . '</label></p>

<p><label for="s_updurl">' .
form::checkbox('s_updurl', 1, $s_updurl) .
__('Update post url') . '</label></p>

</div>

<div class="fieldset">
<h4>' . __('Order of publication of entries') . '</h4>

<p><label for="s_sortby">'.__('Order by:') . '</label>' .
form::combo('s_sortby', $sortby_combo, $s_sortby) . '</p>

<p><label for="s_order">'.__('Sort:').'</label>' .
form::combo('s_order', $order_combo, $s_order) . '</p>

</div>';
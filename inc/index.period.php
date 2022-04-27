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

if (!defined('DC_CONTEXT_ADMIN')) {
    return null;
}

dcPage::check('usage,contentadmin');

# Objects
$per = new periodical($core);

# Default values
$action = isset($_POST['action']) ? $_POST['action'] : '';

$starting_script = '';

# Default value for period
$period_id      = null;
$period_title   = __('One post per day');
$period_pub_nb  = 1;
$period_pub_int = 'day';
$period_curdt   = time();
$period_enddt   = time() + 31536000; //one year

# Get period
if (!empty($_REQUEST['period_id'])) {
    $rs = $per->getPeriods([
        'periodical_id' => $_REQUEST['period_id']
    ]);
    if ($rs->isEmpty()) {
        $core->error->add(__('This period does not exist.'));
        $period_id = null;
    } else {
        $period_id      = $rs->periodical_id;
        $period_title   = $rs->periodical_title;
        $period_pub_nb  = $rs->periodical_pub_nb;
        $period_pub_int = $rs->periodical_pub_int;
        $period_curdt   = strtotime($rs->periodical_curdt);
        $period_enddt   = strtotime($rs->periodical_enddt);
    }
}

# Set period
if ($action == 'setperiod') {
    # Get POST values
    if (!empty($_POST['period_title'])) {
        $period_title = $_POST['period_title'];
    }
    if (!empty($_POST['period_pub_nb'])) {
        $period_pub_nb = abs((integer) $_POST['period_pub_nb']);
    }
    if (!empty($_POST['period_pub_int']) 
        && in_array($_POST['period_pub_int'], $per->getTimesCombo())
    ) {
        $period_pub_int = $_POST['period_pub_int'];
    }
    if (!empty($_POST['period_curdt'])) {
        $period_curdt = strtotime($_POST['period_curdt']);
    }
    if (!empty($_POST['period_enddt'])) {
        $period_enddt = strtotime($_POST['period_enddt']);
    }

    # Check period title and dates
    $old_titles = $per->getPeriods([
        'periodical_title' => $period_title
    ]);
    if (!$old_titles->isEmpty()) {
        while($old_titles->fetch()) {
            if (!$period_id || $old_titles->periodical_id != $period_id) {
                $core->error->add(__('Period title is already taken'));
            }
        }
    }
    if (empty($period_title)) {
        $core->error->add(__('Period title is required'));
    }
    if (strtotime($period_curdt) > strtotime($period_enddt)) {
        $core->error->add(__('Start date must be older than end date'));
    }

    # If no error, set period
    if (!$core->error->flag()) {
        $cur = $per->openCursor();
        $cur->periodical_title   = $period_title;
        $cur->periodical_curdt   = $period_curdt;
        $cur->periodical_enddt   = $period_enddt;
        $cur->periodical_pub_int = $period_pub_int;
        $cur->periodical_pub_nb  = $period_pub_nb;

        # Update period
        if ($period_id) {
            $per->updPeriod($period_id, $cur);

            dcPage::addSuccessNotice(
                __('Period successfully updated.')
            );
        # Create period
        } else {
            $period_id = $per->addPeriod($cur);

            dcPage::addSuccessNotice(
                __('Period successfully created.')
            );
        }

        if (!empty($_POST['redir'])) {
            http::redirect($_POST['redir']);
        } else {
            $core->adminurl->redirect('admin.plugin.periodical', ['part' => 'period', 'period_id' => $period_id], '#period');
        }
    }
}

# Actions on related posts
if (!$core->error->flag() && $period_id && $action && !empty($_POST['periodical_entries'])) {
    # Publish posts
    if ($action == 'publish') {
        try {
            foreach($_POST['periodical_entries'] as $id) {
                $id = (integer) $id;
                $core->blog->updPostStatus($id, 1);
                $per->delPost($id);
            }

            dcPage::addSuccessNotice(
                __('Entries successfully published.')
            );

            if (!empty($_POST['redir'])) {
                http::redirect($_POST['redir']);
            } else {
                $core->adminurl->redirect('admin.plugin.periodical', ['part' => 'period', 'period_id' => $period_id], '#posts');
            }
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }

    # Unpublish posts
    if ($action == 'unpublish') {
        try {
            foreach($_POST['periodical_entries'] as $id) {
                $id = (integer) $id;
                $core->blog->updPostStatus($id,0);
                $per->delPost($id);
            }

            dcPage::addSuccessNotice(
                __('Entries successfully unpublished.')
            );

            if (!empty($_POST['redir'])) {
                http::redirect($_POST['redir']);
            } else {
                $core->adminurl->redirect('admin.plugin.periodical', ['part' => 'period', 'period_id' => $period_id], '#posts');
            }
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }

    # Remove posts from periodical
    if ($action == 'remove_post_periodical') {
        try {
            foreach($_POST['periodical_entries'] as $id) {
                $id = (integer) $id;
                $per->delPost($id);
            }

            dcPage::addSuccessNotice(
                __('Entries successfully removed.')
            );

            if (!empty($_POST['redir'])) {
                http::redirect($_POST['redir']);
            } else {
                $core->adminurl->redirect('admin.plugin.periodical', ['part' => 'period', 'period_id' => $period_id], '#posts');
            }
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }
}

# Prepare combos for posts list
if ($period_id) {
    # Filters
    $post_filter = new adminPostFilter($core);
    $post_filter->add('part', 'period');

    $params = $post_filter->params();
    $params['periodical_id'] = $period_id;
    $params['no_content']    = true;

    # Get posts
    try {
        $posts = $per->getPosts($params);
        $counter = $per->getPosts($params, true);
        $post_list = new adminPeriodicalList($core, $posts, $counter->f(0));
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }

    $starting_script =
        dcPage::jsLoad(dcPage::getPF('periodical/js/checkbox.js')) .
        $post_filter->js($core->adminurl->get('admin.plugin.periodical', ['part' => 'period', 'period_id' => $period_id], '&').'#posts');
}

# Display
echo '
<html><head><title>' . __('Periodical') . '</title>' .
dcPage::jsLoad(dcPage::getPF('periodical/js/dates.js')) .
$starting_script .
dcPage::jsDatePicker() .
dcPage::jsPageTabs() .
'</head>
<body>';

echo
dcPage::breadcrumb([
        __('Plugins') => '',
        __('Periodical') => $p_url . '&amp;part=periods',
        (null === $period_id ? __('New period') : __('Edit period')) => ''
]) .
dcPage::notices();

# Period form
echo '
<div id="period"><h3>' . (null === $period_id ? __('New period') : __('Edit period')) . '</h3>
<form method="post" action="' . $p_url . '">

<p><label for="period_title">' . __('Title:') . '</label>' .
form::field('period_title', 60, 255, html::escapeHTML($period_title), 'maximal') . '</p>

<div class="two-boxes">

<p><label for="period_curdt">' . __('Next update:') . '</label>' .
form::datetime('period_curdt', [
    'default' => html::escapeHTML(dt::str('%Y-%m-%dT%H:%M', strtotime($period_curdt))),
    'class'   => (!$period_curdt ? 'invalid' : ''),
]) . '</p>

<p><label for="period_enddt">' . __('End date:') . '</label>' .
form::datetime('period_enddt', [
    'default' => html::escapeHTML(dt::str('%Y-%m-%dT%H:%M', strtotime($period_enddt))),
    'class'   => (!$period_enddt ? 'invalid' : ''),
]) .'</p>

</div><div class="two-boxes">

<p><label for="period_pub_int">' . __('Publication frequency:') . '</label>' .
form::combo('period_pub_int',$per->getTimesCombo(), $period_pub_int) . '</p>

<p><label for="period_pub_nb">' . __('Number of entries to publish every time:') . '</label>' .
form::number('period_pub_nb', ['min' => 1, 'max' => 20, 'default' => $period_pub_nb]) . '</p>

</div>

<div class="clear">
<p><input type="submit" name="save" value="' . __('Save') . '" />' .
$core->formNonce() .
form::hidden(['action'], 'setperiod') .
form::hidden(['period_id'], $period_id) .
form::hidden(['part'], 'period') .'
</p>
</div>
</form>
</div>';

if ($period_id && !$core->error->flag()) {

    # Actions combo box
    $combo_action = [];
    $combo_action[__('Entries')][__('Publish')] = 'publish';
    $combo_action[__('Entries')][__('Unpublish')] = 'unpublish';
    $combo_action[__('Periodical')][__('Remove from periodical')] = 'remove_post_periodical';

    $base_url = $p_url .
        '&amp;period_id=' .$period_id .
        '&amp;part=period' .
        '&amp;user_id=' . $post_filter->user_id .
        '&amp;cat_id=' . $post_filter->cat_id .
        '&amp;status=' . $post_filter->status .
        '&amp;selected=' . $post_filter->selected .
        '&amp;attachment=' . $post_filter->attachment .
        '&amp;month=' . $post_filter->month .
        '&amp;lang=' . $post_filter->lang .
        '&amp;sortby=' . $post_filter->sortby .
        '&amp;order=' . $post_filter->order .
        '&amp;nb=' . $post_filter->nb .
        '&amp;page=%s' .
        '#posts';

    echo '
    <div id="posts"><h3>' . __('Entries linked to this period') . '</h3>';

    # Filters
    $post_filter->display(['admin.plugin.periodical', '#posts'], 
        $core->adminurl->getHiddenFormFields('admin.plugin.periodical', [
            'period_id' => $period_id,
            'part'      => 'period'
        ])
    );

    # Posts list
    $post_list->postDisplay($post_filter, $base_url, 
        '<form action="' . $p_url . '" method="post" id="form-entries">' .

        '%s' .

        '<div class="two-cols">' .
        '<p class="col checkboxes-helpers"></p>' .

        '<p class="col right">' . __('Selected entries action:') . ' ' .
        form::combo('action', $combo_action) .
        '<input type="submit" value="' . __('ok') . '" /></p>' .
        $core->adminurl->getHiddenFormFields('admin.plugin.periodical', array_merge($post_filter->values(), [
            'period_id' => $period_id,
            'redir'     => sprintf($base_url, $post_filter->page)
        ])) .
        $core->formNonce() .
        '</div>' .
        '</form>'
    );

    echo
    '</div>';
}

dcPage::helpBlock('periodical');

echo '</body></html>';
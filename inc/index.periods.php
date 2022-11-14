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

dcPage::check(dcCore::app()->auth->makePermissions([dcAuth::PERMISSION_USAGE, dcAuth::PERMISSION_CONTENT_ADMIN]));

# Objects
$per = new periodical();

# Default values
$action = $_POST['action'] ?? '';

# Delete periods and related posts links
if ($action == 'deleteperiods' && !empty($_POST['periods'])) {
    try {
        foreach ($_POST['periods'] as $id) {
            $id = (int) $id;
            $per->delPeriodPosts($id);
            $per->delPeriod($id);
        }

        dcAdminNotices::addSuccessNotice(
            __('Periods removed.')
        );

        if (!empty($_POST['redir'])) {
            http::redirect($_POST['redir']);
        } else {
            dcCore::app()->adminurl->redirect('admin.plugin.periodical', ['part' => 'periods']);
        }
    } catch (Exception $e) {
        dcCore::app()->error->add($e->getMessage());
    }
}
# Delete periods related posts links (without delete periods)
if ($action == 'emptyperiods' && !empty($_POST['periods'])) {
    try {
        foreach ($_POST['periods'] as $id) {
            $id = (int) $id;
            $per->delPeriodPosts($id);
        }

        dcAdminNotices::addSuccessNotice(
            __('Periods emptied.')
        );

        if (!empty($_POST['redir'])) {
            http::redirect($_POST['redir']);
        } else {
            dcCore::app()->adminurl->redirect('admin.plugin.periodical', ['part' => 'periods']);
        }
    } catch (Exception $e) {
        dcCore::app()->error->add($e->getMessage());
    }
}

$combo_action = [
    __('empty periods')  => 'emptyperiods',
    __('delete periods') => 'deleteperiods',
];

# Filters
$p_filter = new adminGenericFilter(dcCore::app(), 'periodical');
$p_filter->add('part', 'periods');

$params = $p_filter->params();

# Get periods
try {
    $periods     = $per->getPeriods($params);
    $counter     = $per->getPeriods($params, true);
    $period_list = new adminPeriodicalList(dcCore::app(), $periods, $counter->f(0));
} catch (Exception $e) {
    dcCore::app()->error->add($e->getMessage());
}

# Display
echo
'<html><head><title>' . __('Periodical') . '</title>' .
dcPage::jsLoad(dcPage::getPF('periodical/js/checkbox.js')) .
$p_filter->js(dcCore::app()->adminurl->get('admin.plugin.periodical', ['part' => 'periods'])) .
'</head>' .
'<body>' .

dcPage::breadcrumb([
    __('Plugins')    => '',
    __('Periodical') => '',
]) .
dcPage::notices() .

'<p class="top-add">
<a class="button add" href="' . dcCore::app()->admin->getPageURL() . '&amp;part=period">' . __('New period') . '</a>
</p>';

if (isset($period_list)) {
    # Filters
    $p_filter->display('admin.plugin.periodical', form::hidden('p', 'periodical') . form::hidden('part', 'periods'));

    # Periods list
    $period_list->periodDisplay(
        $p_filter,
        '<form action="' . dcCore::app()->admin->getPageURL() . '" method="post" id="form-periods">' .

        '%s' .

        '<div class="two-cols">' .
        '<p class="col checkboxes-helpers"></p>' .

        '<p class="col right">' . __('Selected periods action:') . ' ' .
        form::combo('action', $combo_action) .
        '<input type="submit" value="' . __('ok') . '" /></p>' .
        dcCore::app()->adminurl->getHiddenFormFields('admin.plugin.periodical', array_merge(['p' => 'periodical'], $p_filter->values(true))) .
        dcCore::app()->formNonce() .
        '</div>' .
        '</form>'
    );
}
dcPage::helpBlock('periodical');

echo '</body></html>';

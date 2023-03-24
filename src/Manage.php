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
declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use adminGenericFilter;
use dcAuth;
use dcCore;
use dcNsProcess;
use dcPage;
use Exception;
use form;
use http;

/**
 * Admin page for periods
 */
class Manage extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init == defined('DC_CONTEXT_ADMIN')
            && My::phpCompliant()
            && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                dcAuth::PERMISSION_USAGE,
                dcAuth::PERMISSION_CONTENT_ADMIN,
            ]), dcCore::app()->blog->id);

        // call period manage page
        if (($_REQUEST['part'] ?? 'periods') === 'period') {
            static::$init = ManagePeriod::init();
        }

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        if (($_REQUEST['part'] ?? 'periods') === 'period') {
            return ManagePeriod::process();
        }

        # Default values
        $vars = ManageVars::init();

        # Delete periods and related posts links
        if ($vars->action == 'deleteperiods' && !empty($vars->periods)) {
            try {
                foreach ($vars->periods as $id) {
                    Utils::delPeriodPosts($id);
                    Utils::delPeriod($id);
                }

                dcPage::addSuccessNotice(
                    __('Periods removed.')
                );

                if (!empty($vars->redir)) {
                    http::redirect($vars->redir);
                } else {
                    dcCore::app()->adminurl->redirect('admin.plugin.' . My::id(), ['part' => 'periods']);
                }
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }
        }

        # Delete periods related posts links (without delete periods)
        if ($vars->action == 'emptyperiods' && !empty($vars->periods)) {
            try {
                foreach ($vars->periods as $id) {
                    Utils::delPeriodPosts($id);
                }

                dcPage::addSuccessNotice(
                    __('Periods emptied.')
                );

                if (!empty($vars->redir)) {
                    http::redirect($vars->redir);
                } else {
                    dcCore::app()->adminurl->redirect('admin.plugin.' . My::id(), ['part' => 'periods']);
                }
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!static::$init) {
            return;
        }

        if (($_REQUEST['part'] ?? 'periods') === 'period') {
            ManagePeriod::render();

            return;
        }

        # Filters
        $p_filter = new adminGenericFilter(dcCore::app(), My::id());
        $p_filter->add('part', 'periods');

        $params = $p_filter->params();

        # Get periods
        try {
            $periods     = Utils::getPeriods($params);
            $counter     = Utils::getPeriods($params, true);
            $period_list = new ManageList(dcCore::app(), $periods, $counter->f(0));
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        # Display
        dcPage::openModule(
            My::name(),
            dcPage::jsModuleLoad(My::id() . '/js/checkbox.js') .
            $p_filter->js(dcCore::app()->adminurl->get('admin.plugin.' . My::id(), ['part' => 'periods']))
        );

        echo dcPage::breadcrumb([
            __('Plugins') => '',
            My::name()    => '',
        ]) .
        dcPage::notices() .

        '<p class="top-add">
        <a class="button add" href="' . dcCore::app()->admin->getPageURL() . '&amp;part=period">' . __('New period') . '</a>
        </p>';

        if (isset($period_list)) {
            # Filters
            $p_filter->display('admin.plugin.' . My::id(), form::hidden('p', My::id()) . form::hidden('part', 'periods'));

            # Periods list
            $period_list->periodDisplay(
                $p_filter,
                '<form action="' . dcCore::app()->admin->getPageURL() . '" method="post" id="form-periods">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right">' . __('Selected periods action:') . ' ' .
                form::combo('action', My::periodsActionCombo()) .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                dcCore::app()->adminurl->getHiddenFormFields('admin.plugin.' . My::id(), array_merge(['p' => My::id()], $p_filter->values(true))) .
                dcCore::app()->formNonce() .
                '</div>' .
                '</form>'
            );
        }
        dcPage::helpBlock('periodical');

        dcPage::closeModule();
    }
}

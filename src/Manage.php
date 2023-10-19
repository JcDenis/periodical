<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use Dotclear\App;
use Dotclear\Core\Process;
use Dotclear\Core\Backend\{
    Notices,
    Page
};
use Dotclear\Core\Backend\Filter\Filters;
use Dotclear\Helper\Html\Form\{
    Hidden,
    Select
};
use Dotclear\Helper\Network\Http;
use Exception;

/**
 * @brief       periodical manage periods class.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Manage extends Process
{
    public static function init(): bool
    {
        self::status(My::checkContext(My::MANAGE));

        // call period manage page
        if (($_REQUEST['part'] ?? 'periods') === 'period') {
            self::status(ManagePeriod::init());
        }

        return self::status();
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // call period manage page
        if (($_REQUEST['part'] ?? 'periods') === 'period') {
            return ManagePeriod::process();
        }

        // load default values
        $vars = ManageVars::init();

        // Delete periods and related posts links
        if ($vars->action == 'deleteperiods' && !empty($vars->periods)) {
            try {
                foreach ($vars->periods as $id) {
                    Utils::delPeriodPosts($id);
                    Utils::delPeriod($id);
                }

                Notices::addSuccessNotice(
                    __('Periods removed.')
                );

                if (!empty($vars->redir)) {
                    Http::redirect($vars->redir);
                } else {
                    My::redirect(['part' => 'periods']);
                }
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        // Delete periods related posts links (without delete periods)
        if ($vars->action == 'emptyperiods' && !empty($vars->periods)) {
            try {
                foreach ($vars->periods as $id) {
                    Utils::delPeriodPosts($id);
                }

                Notices::addSuccessNotice(
                    __('Periods emptied.')
                );

                if (!empty($vars->redir)) {
                    Http::redirect($vars->redir);
                } else {
                    My::redirect(['part' => 'periods']);
                }
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        // call period manage page
        if (($_REQUEST['part'] ?? 'periods') === 'period') {
            ManagePeriod::render();

            return;
        }

        // Filters
        $p_filter = new Filters(My::id());
        $p_filter->add('part', 'periods');

        $params = $p_filter->params();

        // Get periods
        try {
            $periods     = Utils::getPeriods($params);
            $counter     = Utils::getPeriods($params, true);
            $period_list = new ManageList($periods, $counter->f(0));
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        // Display
        Page::openModule(
            My::name(),
            My::jsLoad('checkbox') .
            $p_filter->js(My::manageUrl(['part' => 'periods']))
        );

        echo Page::breadcrumb([
            __('Plugins') => '',
            My::name()    => '',
        ]) .
        Notices::getNotices() .

        '<p class="top-add">
        <a class="button add" href="' . My::manageUrl(['part' => 'period']) . '">' . __('New period') . '</a>
        </p>';

        if (isset($period_list)) {
            // Filters
            $p_filter->display('admin.plugin.' . My::id(), (new Hidden('part', 'periods'))->render());

            // Periods list
            $period_list->periodDisplay(
                $p_filter,
                '<form action="' . My::manageUrl() . '" method="post" id="form-periods">' .

                '%s' .

                '<div class="two-cols">' .
                '<p class="col checkboxes-helpers"></p>' .

                '<p class="col right">' . __('Selected periods action:') . ' ' .
                (new Select('action'))->items(My::periodsActionCombo())->render() .
                '<input type="submit" value="' . __('ok') . '" /></p>' .
                My::parsedHiddenFields($p_filter->values(true)) .
                '</div>' .
                '</form>'
            );
        }
        Page::helpBlock('periodical');

        Page::closeModule();
    }
}

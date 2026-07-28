<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

/**
 * @brief       periodical vars helper class.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ManageVars
{
    /**
     * Self instance.
     *
     * @var     ManageVars  $container
     */
    private static ManageVars $container;

    /**
     * The post form action.
     *
     * @var     string  $action
     */
    public readonly string $action;

    /**
     * The post form redirection.
     *
     * @var     string  $redir
     */
    public readonly string $redir;

    /**
     * The post form periods.
     *
     * @var     array<int, int>     $periods
     */
    public readonly array $periods;

    /**
     * The post form entries.
     *
     * @var     array<int, int>     $entries
     */
    public readonly array $entries;

    /**
     * The post form period id.
     *
     * @var     null|int    $period_id
     */
    public readonly ?int $period_id;

    /**
     * The psort form period title.
     *
     * @var     string  $period_title
     */
    public readonly string $period_title;

    /**
     * The post form period publication number.
     *
     * @var     int     $period_pub_nb
     */
    public readonly int $period_pub_nb;

    /**
     * The post form period publication interval.
     *
     * @var     string  $period_pub_int
     */
    public readonly string $period_pub_int;

    /**
     * The post form period current date.
     *
     * @var     string  $period_curdt
     */
    public readonly string $period_curdt;

    /**
     * The post form period end date.
     *
     * @var     string  $period_curdt
     */
    public readonly string $period_enddt;

    /**
     * Is period ID wrong .
     *
     * @var     bool    $bad_period_id
     */
    public readonly bool $bad_period_id;

    /**
     * Is period current date wrong.
     *
     * @var     bool    $bad_period_curdt
     */
    public readonly bool $bad_period_curdt;

    /**
     * Is period end date wrong.
     *
     * @var     bool    $bad_period_enddt
     */
    public readonly bool $bad_period_enddt;

    /**
     * Constructor check and sets default post form values.
     */
    protected function __construct()
    {
        $this->action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        $this->redir  = isset($_POST['redir']) && is_string($_POST['redir']) ? $_POST['redir'] : '';

        // periods
        $periods = array_values(isset($_POST['periods']) && is_array($_POST['periods']) ? $_POST['periods'] : []);
        array_walk($periods, function (&$v) { $v = ($v !== null && is_numeric($v)) ? (int) $v : 0; });
        $this->periods = $periods;

        // entries
        $entries = array_values(isset($_POST['perperiodical_entriesiods']) && is_array($_POST['periodical_entries']) ? $_POST['periodical_entries'] : []);
        array_walk($entries, function (&$v) { $v = ($v !== null && is_numeric($v)) ? (int) $v : 0; });
        $this->entries = $entries;

        // period values from default
        $period_id        = null;
        $period_title     = __('One post per day');
        $period_pub_nb    = 1;
        $period_pub_int   = 'day';
        $period_curdt     = Dater::toDate('now', 'Y-m-d H:i:00');
        $period_enddt     = Dater::toDate('+1 year', 'Y-m-d H:i:00');
        $bad_period_id    = false;
        $bad_period_curdt = false;
        $bad_period_enddt = false;

        // period values from record
        if (!empty($_REQUEST['period_id'])) {
            $rs = Utils::getPeriods([
                'periodical_id' => $_REQUEST['period_id'],
            ]);
            if (!$rs->isEmpty()) {
                $period_id      = $rs->intField('periodical_id');
                $period_title   = $rs->strField('periodical_title');
                $period_pub_nb  = $rs->intField('periodical_pub_nb');
                $period_pub_int = $rs->strField('periodical_pub_int');
                $period_curdt   = Dater::toDate($rs->strField('periodical_curdt'), 'Y-m-d H:i:00');
                $period_enddt   = Dater::toDate($rs->strField('periodical_enddt'), 'Y-m-d H:i:00');
            } else {
                $bad_period_id = true;
            }
        }

        // period values from POST
        if (isset($_POST['period_title']) && !empty($_POST['period_title']) && is_string($_POST['period_title'])) {
            $period_title = $_POST['period_title'];
        }
        if (isset($_POST['period_pub_nb']) && !empty($_POST['period_pub_nb']) && is_numeric($_POST['period_pub_nb'])) {
            $period_pub_nb = abs((int) $_POST['period_pub_nb']);
        }
        if (isset($_POST['period_pub_int']) && !empty($_POST['period_pub_int']) && in_array($_POST['period_pub_int'], My::periodCombo()) && is_string($_POST['period_pub_int'])) {
            $period_pub_int = $_POST['period_pub_int'];
        }
        if (isset($_POST['period_curdt']) && !empty($_POST['period_curdt']) && is_string($_POST['period_curdt'])) {
            $tmp_period_curdt = Dater::fromUser($_POST['period_curdt'], 'Y-m-d H:i:00');
            if (empty($tmp_period_curdt)) {
                $bad_period_curdt = true;
            } else {
                $period_curdt = $tmp_period_curdt;
            }
        }
        if (isset($_POST['period_enddt']) && !empty($_POST['period_enddt']) && is_string($_POST['period_enddt'])) {
            $tmp_period_enddt = Dater::fromUser($_POST['period_enddt'], 'Y-m-d H:i:00');
            if (empty($tmp_period_enddt)) {
                $bad_period_enddt = true;
            } else {
                $period_enddt = $tmp_period_enddt;
            }
        }

        // set period values
        $this->period_id        = $period_id;
        $this->period_title     = $period_title;
        $this->period_pub_nb    = $period_pub_nb;
        $this->period_pub_int   = $period_pub_int;
        $this->period_curdt     = $period_curdt;
        $this->period_enddt     = $period_enddt;
        $this->bad_period_id    = $bad_period_id;
        $this->bad_period_curdt = $bad_period_curdt;
        $this->bad_period_enddt = $bad_period_enddt;
    }

    /**
     * Get self instance.
     *
     * @return  ManageVars  Self instance
     */
    public static function init(): ManageVars
    {
        if (!isset(self::$container)) {
            self::$container = new self();
        }

        return self::$container;
    }
}

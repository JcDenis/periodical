<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical;

use DateTimeZone;
use Dotclear\App;
use Exception;

/**
 * @brief       periodical date helper.
 * @ingroup     periodical
 *
 * Tools to manupilate period date
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Dater
{
    /**
     * Format a date from UTC to user TZ.
     *
     * @param   string  $date       The date
     * @param   string  $format     The output format
     *
     * @return  string  The formated date on user timezone
     */
    public static function fromUser(string $date, string $format = 'Y-m-d H:i:00'): string
    {
        $tz = App::auth()->getInfo('user_tz');
        $d  = date_create($date, new DateTimeZone($tz ?? 'UTC'));

        return $d ? date_format($d->setTimezone(new DateTimeZone('UTC')), $format) : '';
    }

    /**
     * Format a date from user TZ to UTC.
     *
     * @param   string  $date       The date
     * @param   string  $format     The output format
     *
     * @return  string  The formated date on UTC
     */
    public static function toUser(string $date, string $format = 'Y-m-d\TH:i'): string
    {
        $tz = App::auth()->getInfo('user_tz');
        $d  = date_create($date, new DateTimeZone('UTC'));

        return $d ? date_format($d->setTimezone(new DateTimeZone($tz ?? 'UTC')), $format) : '';
    }

    /**
     * Format a date to specific TZ (UTC by default) from another format.
     *
     * @param   string  $date       The date
     * @param   string  $format     The output format
     * @param   string  $to_tz      The output timezone
     *
     * @return  string  The formated date
     */
    public static function toDate(int|string $date = 'now', string $format = 'Y-m-d H:i:00', string $to_tz = 'UTC'): string
    {
        $d = is_int($date) ?
            date_create_from_format('U', (string) $date, new DateTimeZone('UTC')) :
            date_create($date, new DateTimeZone('UTC'));

        return $d ? date_format($d->setTimeZone(new DateTimeZone($to_tz)), $format) : '';
    }

    /**
     * Get next timestamp from a period.
     *
     * @param   int     $ts         The timestamp
     * @param   string  $period     The period (periodical string format)
     *
     * @return  int  The timestamp of next update
     */
    public static function getNextTime(int $ts, string $period): int
    {
        $dt = date_create_from_format('U', (string) $ts);

        if ($dt === false) {
            return $ts;
        }

        switch($period) {
            case 'hour':
                $dt->modify('+1 hour');

                break;

            case 'halfday':
                $dt->modify('+12 hours');

                break;

            case 'day':
                $dt->modify('+1 day');

                break;

            case 'week':
                $dt->modify('+1 week');

                break;

            case 'month':
                $dt->modify('+1 month');

                break;

            default:

                throw new Exception(__('Unknow frequence'));
        }

        return (int) $dt->format('U');
    }
}

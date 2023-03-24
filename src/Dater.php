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

use DateTimeZone;
use dcCore;
use Exception;

/**
 * Tools to manupilate period date
 */
class Dater
{
    /**
     * Format a date from UTC to user TZ
     */
    public static function fromUser(string $date, string $format = 'Y-m-d H:i:00'): string
    {
        $d = date_create($date, new DateTimeZone(dcCore::app()->auth->getInfo('user_tz')));

        return $d ? date_format($d->setTimezone(new DateTimeZone('UTC')), $format) : '';
    }

    /**
     * Format a date from user TZ to UTC
     */
    public static function toUser(string $date, string $format = 'Y-m-d\TH:i'): string
    {
        $d = date_create($date, new DateTimeZone('UTC'));

        return $d ? date_format($d->setTimezone(new DateTimeZone(dcCore::app()->auth->getInfo('user_tz'))), $format) : '';
    }

    /**
     * Format a date to specific TZ (UTC by default) from another format
     */
    public static function toDate(int|string $date = 'now', string $format = 'Y-m-d H:i:00', string $to_tz = 'UTC'): string
    {
        $d = is_int($date) ?
            date_create_from_format('U', (string) $date, new DateTimeZone('UTC')) :
            date_create($date, new DateTimeZone('UTC'));

        return $d ? date_format($d->setTimeZone(new DateTimeZone($to_tz)), $format) : '';
    }

    /**
     * Get next timestamp from a period
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

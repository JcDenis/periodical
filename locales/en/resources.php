<?php

/**
 * @file
 * @brief       The plugin periodical help resource
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

\Dotclear\App::backend()->resources()
    ->set('help', 'periodical-periods', __DIR__ . '/help/periods.html')
    ->set('help', 'periodical-period', __DIR__ . '/help/period.html');

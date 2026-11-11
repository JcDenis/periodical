<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical\ClaudetteMcp;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Plugin\ClaudetteMcp\Tool\PostTool;
use Dotclear\Plugin\periodical\My;

/**
 * @brief       periodical tools helper for the plugin ClaudetteMcp.
 * @ingroup     periodical
 *
 * ClaudetteMcp connects an AI assistant to the blog: these tools let the assistant use periods.
 * These classes are only loaded by ClaudetteMcp, which is not required by periodical.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
abstract class PeriodTool extends PostTool
{
    /**
     * Like in backend: periodical must be enabled on the blog, and the user must be able to write entries.
     */
    public function available(): bool
    {
        return My::settings()->getBool('periodical_active', false) && $this->canWrite();
    }

    /**
     * Get the period ID of an entry, 0 if none.
     *
     * Whatever the status of the entry, not like Utils::getPosts().
     */
    protected function periodOf(int $post_id): int
    {
        $sql = new SelectStatement();
        $rs  = $sql
            ->column('meta_id')
            ->from(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME)
            ->where('meta_type = ' . $sql->quote(My::id()))
            ->and('post_id = ' . $post_id)
            ->select();

        return $rs instanceof MetaRecord && !$rs->isEmpty() ? $rs->intField('meta_id') : 0;
    }

    /**
     * Describe current period of a record.
     *
     * @return  array<string, mixed>
     */
    protected function describePeriod(MetaRecord $rs): array
    {
        return [
            'id'       => $rs->intField('periodical_id'),
            'title'    => $rs->strField('periodical_title'),
            'next'     => $rs->strField('periodical_curdt'),
            'end'      => $rs->strField('periodical_enddt'),
            'interval' => $rs->strField('periodical_pub_int'),
            'number'   => $rs->intField('periodical_pub_nb'),
        ];
    }
}

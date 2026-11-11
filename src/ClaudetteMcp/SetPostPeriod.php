<?php

declare(strict_types=1);

namespace Dotclear\Plugin\periodical\ClaudetteMcp;

use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Dotclear\Plugin\periodical\Utils;

/**
 * @brief       periodical tool for the plugin ClaudetteMcp: link a post to a period.
 * @ingroup     periodical
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class SetPostPeriod extends PeriodTool
{
    public function name(): string
    {
        return 'set_post_period';
    }

    public function title(): string
    {
        return 'Link a blog post to a period';
    }

    public function description(): string
    {
        return 'Link a post to a period of periodical publication (see list_periods), or remove it from its period. ' .
            'A post has only one period. The blog then publishes the post by itself at the next date of the period, ' .
            'but only if the status of the post is "pending" (see set_post_status). ' .
            'A post can be changed only by its author or by a content administrator. ' .
            'Only do this when the user explicitly asks for it.';
    }

    public function properties(): array
    {
        return [
            'post_id' => [
                'type'        => 'integer',
                'description' => 'The post ID (see list_posts).',
            ],
            'period_id' => [
                'type'        => 'integer',
                'description' => 'The period ID (see list_periods), 0 to remove the post from its period.',
            ],
        ];
    }

    public function required(): array
    {
        return ['post_id', 'period_id'];
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function call(array $args): array
    {
        $post_id   = $this->int($args, 'post_id');
        $period_id = $this->int($args, 'period_id');
        $post      = $this->fetch($post_id);

        if (!$this->editable($post)) {
            throw new McpToolException('You are not allowed to update this entry');
        }

        if ($post->strField('post_type') !== 'post') {
            throw new McpToolException('Only posts can be linked to a period');
        }

        $period = null;
        if ($period_id > 0) {
            $rs = Utils::getPeriods(['periodical_id' => $period_id]);
            if ($rs->isEmpty()) {
                throw new McpToolException('Unknown period, see list_periods');
            }

            $period = $this->describePeriod($rs);
        }

        if ($this->periodOf($post_id) !== $period_id) {
            // A post has only one period
            Utils::delPost($post_id);
            if ($period_id > 0) {
                Utils::addPost($period_id, $post_id);
            }
        }

        $status = array_search($post->intField('post_status'), $this->statuses(), true);

        return [
            'id'     => $post_id,
            'status' => $status === false ? 'unknown' : $status,
            'period' => $period,
        ];
    }
}

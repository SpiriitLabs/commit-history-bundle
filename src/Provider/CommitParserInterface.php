<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Provider;

use Spiriit\Bundle\CommitHistoryBundle\DTO\Commit;

interface CommitParserInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): Commit;
}

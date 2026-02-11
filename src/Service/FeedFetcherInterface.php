<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\Service;

use Spiriit\CommitHistory\Service\FeedFetcherInterface as BaseFeedFetcherInterface;

/**
 * Bundle-level FeedFetcher interface.
 * Extends the library's interface to provide a bundle-specific type-hint.
 */
interface FeedFetcherInterface extends BaseFeedFetcherInterface
{
}

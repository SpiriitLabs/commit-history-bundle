<?php

declare(strict_types=1);

/*
 * This file is part of the spiriitlabs/commit-history-bundle package.
 * Copyright (c) SpiriitLabs <https://www.spiriit.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiriit\Bundle\CommitHistoryBundle\DTO;

readonly class Commit
{
    public function __construct(
        public string $id,
        public string $title,
        public \DateTimeImmutable $date,
        public string $author,
        public string $url,
        public ?string $authorEmail = null,
        public bool $hasDependenciesChanges = false,
    ) {
    }

    public function withHasDependenciesChanges(bool $hasDependenciesChanges): self
    {
        return new self(
            $this->id,
            $this->title,
            $this->date,
            $this->author,
            $this->url,
            $this->authorEmail,
            $hasDependenciesChanges,
        );
    }
}

<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class BboxQuery
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Type('float')]
        public readonly float $min_lon = 0,

        #[Assert\NotNull]
        #[Assert\Type('float')]
        public readonly float $min_lat = 0,

        #[Assert\NotNull]
        #[Assert\Type('float')]
        public readonly float $max_lon = 0,

        #[Assert\NotNull]
        #[Assert\Type('float')]
        public readonly float $max_lat = 0,
    ) {
    }
}

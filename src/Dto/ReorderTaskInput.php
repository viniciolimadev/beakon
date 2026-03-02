<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ReorderTaskInput
{
    #[Assert\NotNull(message: 'order is required.')]
    #[Assert\PositiveOrZero(message: 'order must be zero or positive.')]
    public int $order = 0;
}

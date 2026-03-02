<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ChangeTaskStatusInput
{
    #[Assert\NotBlank(message: 'Status is required.')]
    #[Assert\Choice(choices: ['inbox', 'today', 'in_progress', 'done'], message: 'Invalid status.')]
    public string $status = '';
}

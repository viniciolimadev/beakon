<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTaskInput
{
    #[Assert\NotBlank(message: 'Title is required.')]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\Choice(choices: ['inbox', 'today', 'in_progress', 'done'], message: 'Invalid status.')]
    public string $status = 'inbox';

    #[Assert\Choice(choices: ['low', 'medium', 'high'], message: 'Invalid priority.')]
    public string $priority = 'medium';

    #[Assert\PositiveOrZero(message: 'estimated_minutes must be zero or positive.')]
    public ?int $estimatedMinutes = null;

    public ?string $dueDate = null;
}

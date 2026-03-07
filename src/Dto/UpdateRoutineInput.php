<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateRoutineInput
{
    #[Assert\NotBlank(message: 'Title is required.')]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\NotBlank(message: 'time_of_day is required.')]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'time_of_day must be in HH:MM format.')]
    public string $timeOfDay = '';

    #[Assert\NotBlank(message: 'days_of_week is required.')]
    #[Assert\Count(min: 1, minMessage: 'days_of_week must have at least one day.')]
    #[Assert\All([
        new Assert\Range(min: 0, max: 6, notInRangeMessage: 'Each day must be between 0 (Sunday) and 6 (Saturday).'),
    ])]
    public array $daysOfWeek = [];

    #[Assert\PositiveOrZero(message: 'order must be zero or positive.')]
    public int $order = 0;
}

<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTaskInput
{
    #[Assert\NotBlank(message: 'Title is required.')]
    #[Assert\Length(max: 255)]
    public string $title = '';
}

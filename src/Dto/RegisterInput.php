<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterInput
{
    #[Assert\NotBlank(message: 'Name is required.')]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Invalid email address.')]
    #[Assert\Length(max: 255)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Password is required.')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters.')]
    public string $password = '';
}

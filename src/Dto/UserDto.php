<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Annotations as OA;

class UserDto
{
    /**
     * @Assert\NotBlank( message="Name is mandatory" )
     * @Assert\Email( message="Invalid email address" )
     * @OA\Property (default="user@example.com")
     */
    public string $username;

    /**
     * @Assert\NotBlank( message="Password is mandatory" )
     * @Assert\Length( min=6, minMessage="Password must contain at least {{ limit }} characters" )
     * @OA\Property (default="user_password")
     */
    public string $password;

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}

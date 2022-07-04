<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserService
{
    private JWTTokenManagerInterface $jwtManager;
    private TokenStorageInterface $tokenStorageInterface;
    private UserRepository $userRepository;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        TokenStorageInterface    $tokenStorageInterface,
        UserRepository           $userRepository
    ) {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->userRepository = $userRepository;
    }

    /**
     * @throws JWTDecodeFailureException
     */
    public function getFromStorage(): User
    {
        $token = $this->tokenStorageInterface->getToken();
        if (null === $token) {
            throw new UnauthorizedHttpException('Access token not presented');
        }
        $decodedJwtToken = $this->jwtManager->decode($token);

        return $this->userRepository->findOneBy([
            'email' => $decodedJwtToken['username']
        ]);
    }
}

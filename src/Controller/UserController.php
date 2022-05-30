<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use OpenApi\Annotations as OA;

class UserController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;
    private TokenStorageInterface $tokenStorageInterface;

    public function __construct(TokenStorageInterface $tokenStorageInterface, JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
    }

    /**
     * @Route("/api/v1/users/current", name="app_user", methods={"GET"})
     * @OA\Get(
     *     description="Get user data by JWT",
     *     tags={"user"},
     *     @OA\Response(
     *          response=200,
     *          description="The user data",
     *          @OA\JsonContent(
     *              @OA\Schema(
     *                  schema="CurrentUser",
     *                  type="object",
     *                  @OA\Property(property="username", type="string"),
     *                  @OA\Property(
     *                      property="roles",
     *                      type="array",
     *                      @OA\Items(type="string")
     *                  ),
     *                  @OA\Property(property="balance", type="float")
     *              )
     *          )
     *     )
     * )
     * @Security(name="Bearer")
     * @throws JWTDecodeFailureException
     */
    public function getCurrent(UserRepository $userRepository): JsonResponse
    {
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());

        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => $decodedJwtToken['username']
        ]);

        return $this->json([
            'username' => $decodedJwtToken['username'],
            'roles' => $decodedJwtToken['roles'],
            'balance' => $user->getBalance(),
        ]);
    }
}

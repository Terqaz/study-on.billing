<?php

namespace App\Controller;

use App\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    /**
     * @Route("/api/v1/users/current", name="app_user", methods={"GET"})
     * @OA\Get(
     *     description="Get user data by JWT",
     *     tags={"user"},
     *     @OA\Response(
     *          response=200,
     *          description="The user data",
     *          @OA\JsonContent(
     *              schema="CurrentUser",
     *              type="object",
     *              @OA\Property(property="username", type="string"),
     *              @OA\Property(
     *                  property="roles",
     *                  type="array",
     *                  @OA\Items(type="string")
     *              ),
     *              @OA\Property(property="balance", type="float")
     *          )
     *     )
     * )
     * @Security(name="Bearer")
     * @throws JWTDecodeFailureException
     */
    public function getCurrent(UserService $userService): JsonResponse
    {
        $user = $userService->getFromStorage();

        return $this->json([
            'username' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ]);
    }
}

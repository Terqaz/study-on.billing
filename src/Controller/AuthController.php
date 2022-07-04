<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Exception;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    /**
     * @Route("/api/v1/register", name="app_register", methods={"POST"})
     * @OA\Post(
     *     description="Register new user. Get new JWT and new refresh token",
     *     tags={"auth"},
     *     @OA\RequestBody(
     *          @Model(type=UserDto::class)
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="Returns the JWT token",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="token", type="string"),
     *              @OA\Property(property="refresh_token", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=409,
     *          description="User already exists",
     *          @OA\JsonContent(
     *              schema="Error",
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     )
     * )
     */
    public function register(
        Request                  $request,
        UserRepository           $userRepository,
        ValidatorInterface       $validator,
        JWTTokenManagerInterface $JWTManager,
        UserPasswordHasherInterface $userPasswordHasher,
        RefreshTokenGeneratorInterface $refreshTokenGenerator
    ): JsonResponse {
        $serializer = SerializerBuilder::create()->build();

        $userDto = $serializer->deserialize($request->getContent(), UserDto::class, 'json');

        $errors = $validator->validate($userDto);
        if (count($errors) > 0) {
            return $this->json([
                'errors' => (string) $errors,
            ], 400);
        }

        $user = User::fromDto($userDto)
            ->setRoles(['ROLE_USER']);
        $user->setPassword($userPasswordHasher->hashPassword(
            $user,
            $user->getPassword()
        ));

        $refreshToken = $refreshTokenGenerator->createForUserWithTtl($user, 2592000); # 1 month
        try {
            $userRepository->add($user, true);
            return $this->json([
                'token' => $JWTManager->create($user),
                'refresh_token' => $refreshToken->getRefreshToken(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }
    }

    /**
     * @Route("/api/v1/auth", name="login", methods={"POST"})
     * @OA\Post(
     *     description="Get JWT and new refresh token by credentials",
     *     tags={"auth"},
     *     @OA\RequestBody(
     *          @Model(type=UserDto::class)
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="The JWT token",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="token", type="string"),
     *              @OA\Property(property="refresh_token", type="string")
     *          )
     *     )
     * )
     * Managed by lexik/jwt-authentication-bundle. Used for only OA doc
     * @throws Exception
     */
    public function login(): JsonResponse
    {
        throw new RuntimeException();
    }

    /**
     * @Route("/api/v1/token/refresh", name="refresh", methods={"POST"})
     * @OA\Post(
     *     description="Get new valid JWT token renewing valid datetime of presented refresh token",
     *     tags={"auth"},
     *     @OA\RequestBody(
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="refresh_token", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="The JWT token",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="token", type="string"),
     *              @OA\Property(property="refresh_token", type="string")
     *          )
     *     )
     * )make
     * Managed by gesdinet/jwt-refresh-token-bundle. Used for only OA doc
     * @throws Exception
     */
    public function refresh(): JsonResponse
    {
        throw new RuntimeException();
    }
}

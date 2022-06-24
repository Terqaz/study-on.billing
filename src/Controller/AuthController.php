<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Annotations as OA;

class AuthController extends AbstractController
{
    /**
     * @Route("/api/v1/register", name="app_register", methods={"POST"})
     * @OA\Post(
     *     description="Register new user. Get new JWT",
     *     tags={"auth"},
     *     @OA\RequestBody(
     *          @Model(type=UserDto::class)
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="Returns the JWT token",
     *          @OA\JsonContent(
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="token", type="string")
     *              )
     *          )
     *     )
     * )
     */
    public function register(
        Request                  $request,
        UserRepository           $userRepository,
        ValidatorInterface       $validator,
        JWTTokenManagerInterface $JWTManager,
        UserPasswordHasherInterface $userPasswordHasher
    ): JsonResponse {

        $serializer = SerializerBuilder::create()->build();

        $userDto = $serializer->deserialize($request->getContent(), UserDto::class, 'json');

        $errors = $validator->validate($userDto);
        if (count($errors) > 0) {
            return $this->json([
                'errors' => (string) $errors,
            ], 400);
        }

        $user = User::fromDto($userDto);
        $user->setPassword($userPasswordHasher->hashPassword(
            $user,
            $user->getPassword()
        ));

        try {
            $userRepository->add($user, true);
            return $this->json([
                'token' => $JWTManager->create($user),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }
    }

    /**
     * @Route("/api/v1/auth", name="login", methods={"POST"})
     * @OA\Post(
     *     description="Get JWT by credentials",
     *     tags={"auth"},
     *     @OA\RequestBody(
     *          @Model(type=UserDto::class)
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="The JWT token",
     *          @OA\JsonContent(
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="token", type="string")
     *              )
     *          )
     *     )
     * )
     */
    public function login(): JsonResponse
    {
        return $this->json([]);
    }
}

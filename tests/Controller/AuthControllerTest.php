<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\AbstractTest;
use JsonException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuthControllerTest extends AbstractTest
{

    /**
     * @throws JsonException
     * @throws JWTDecodeFailureException
     */
    public function testRegisterThenAuth(): void
    {
        $client = static::getClient();
        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = $client->getContainer()->get(JWTTokenManagerInterface::class);

        $email = "new.user@example.com";
        $password = "1234567";

        // Нет username
        $client->jsonRequest('POST', '/api/v1/register', [
            "password" => "string"
        ]);
        $this->assertResponseCode(400);
        // Нет password
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => "string",
        ]);
        $this->assertResponseCode(400);
        // Пароль меньше 6 символов
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => $email,
            "password" => "12345"
        ]);
        $this->assertResponseCode(400);
        // Неверный email
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => "@example.com",
            "password" => $password
        ]);
        $this->assertResponseCode(400);

        // Email занят
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => 'user@example.com',
            "password" => $password
        ]);
        $this->assertResponseCode(409);

        // Все верно
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(200);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue(isset($responseData['token']));


        /** @var UserRepository $userRepository */
        $userRepository = self::getEntityManager()->getRepository(User::class);
        self::assertEquals(1, $userRepository->count(['email' => $email]));

        // Try login
        // Нет username
        $client->jsonRequest('POST', '/api/v1/auth', [
            "password" => "string"
        ]);
        $this->assertResponseCode(400);
        // Нет password
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => "string",
        ]);
        $this->assertResponseCode(400);
        // Неверный email
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => "new.uuuuuuuuuser@example.com",
            "password" => $password
        ]);
        $this->assertResponseCode(401);
        // Неверный password
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => $email,
            "password" => "1234567777777777777"
        ]);
        $this->assertResponseCode(401);

        // Все верно
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(200);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue(isset($responseData['token']));
    }
}

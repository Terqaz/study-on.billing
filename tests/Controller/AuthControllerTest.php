<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\AbstractTest;

class AuthControllerTest extends AbstractTest
{
    private const NEW_EMAIL = 'new.user@example.com';
    private const NEW_PASSWORD = 'new.user_password';

    public function testRegister(): void
    {
        $client = static::getClient();

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
            "username" => self::NEW_EMAIL,
            "password" => "12345"
        ]);
        $this->assertResponseCode(400);
        // Неверный email
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => "@example.com",
            "password" => self::NEW_PASSWORD
        ]);
        $this->assertResponseCode(400);

        // Email занят
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => self::EMAIL,
            "password" => self::NEW_PASSWORD
        ]);
        $this->assertResponseCode(409);

        // Все верно
        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => self::NEW_EMAIL,
            "password" => self::NEW_PASSWORD
        ]);
        $this->assertResponseCode(200);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue(isset($responseData['token']));
        self::assertTrue(isset($responseData['refresh_token']));


        /** @var UserRepository $userRepository */
        $userRepository = self::getEntityManager()->getRepository(User::class);
        self::assertEquals(1, $userRepository->count(['email' => self::NEW_EMAIL]));
    }

    public function testAuth(): void
    {
        $client = static::getClient();

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
            "password" => self::PASSWORD
        ]);
        $this->assertResponseCode(401);
        // Неверный password
        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => self::EMAIL,
            "password" => "1234567777777777777"
        ]);
        $this->assertResponseCode(401);

        // Все верно
        $responseData = $this->login($client, self::EMAIL, self::PASSWORD);

        self::assertTrue(isset($responseData['token']));
        self::assertTrue(isset($responseData['refresh_token']));
    }

    public function testRegisterThenAuth(): void
    {
        $client = static::getClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            "username" => self::NEW_EMAIL,
            "password" => self::NEW_PASSWORD
        ]);
        $this->assertResponseCode(200);

        $this->login($client, self::NEW_EMAIL, self::NEW_PASSWORD);

        $client->jsonRequest('GET', '/api/v1/users/current');
    }

    public function testRefreshToken(): void
    {
        $client = static::getClient();

        $authResponseData = $this->login($client, self::EMAIL, self::PASSWORD);

        $client->request('GET', '/api/v1/users/current');
        $this->assertResponseCode(200);

        $this->logout($client);

        $client->request('GET', '/api/v1/users/current');
        $this->assertResponseCode(401);

        // Несуществующий токен
        $client->request('POST', '/api/v1/token/refresh', [
            'refresh_token' => '1'
        ]);
        $this->assertResponseCode(401);

        // Все верно
        $client->request('POST', '/api/v1/token/refresh', [
            'refresh_token' => $authResponseData['refresh_token']
        ]);
        $this->assertResponseCode(200);

        $responseData = self::parseJsonResponse($client);
        self::assertTrue(isset($responseData['token']));
        self::assertTrue(isset($responseData['refresh_token']));
    }
}

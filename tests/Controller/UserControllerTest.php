<?php

namespace App\Tests\Controller;

use App\Tests\AbstractTest;
use JsonException;

class UserControllerTest extends AbstractTest
{
    /**
     * @throws JsonException
     */
    public function testGetCurrentUser(): void
    {
        $client = static::getClient();

        $email = "admin@example.com";
        $password = "admin_password";

        $client->jsonRequest('POST', '/api/v1/auth', [
            "username" => $email,
            "password" => $password
        ]);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request('GET', '/api/v1/users/current', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '. $responseData['token']
        ]);
        $this->assertResponseCode(200);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals($email, $responseData['username']);
        self::assertEquals('ROLE_SUPER_ADMIN', $responseData['roles'][0]);
        self::assertEquals(0, $responseData['balance']);
    }
}

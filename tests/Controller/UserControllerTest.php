<?php

namespace App\Tests\Controller;

use App\Tests\AbstractTest;

class UserControllerTest extends AbstractTest
{
    public function testGetCurrentUser(): void
    {
        $client = static::getClient();

        $email = "admin@example.com";
        $password = "admin_password";

        $this->login($client, $email, $password);

        $client->request('GET', '/api/v1/users/current');
        $this->assertResponseCode(200);

        $responseData = self::parseJsonResponse($client);
        self::assertEquals($email, $responseData['username']);
        self::assertEquals('ROLE_SUPER_ADMIN', $responseData['roles'][0]);
        self::assertEquals(0, $responseData['balance']);
    }
}

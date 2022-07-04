<?php

namespace App\Tests\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Tests\AbstractTest;
use DateInterval;
use DateTime;
use DateTimeInterface;

class CourseControllerTest extends AbstractTest
{
    public function testGetCourses(): void
    {
        $client = static::getClient();

        $client->request('GET', '/api/v1/courses');
        $this->assertResponseOk();

        $courses = self::parseJsonResponse($client);

        self::assertCount(3, $courses);
    }

    public function testGetCourse(): void
    {
        $client = static::getClient();

        $client->request('GET', '/api/v1/courses/interactive-sql-trainer');
        $this->assertResponseOk();

        $course = self::parseJsonResponse($client);

        self::assertEquals('interactive-sql-trainer', $course['code']);
        self::assertEquals('free', $course['type']);
        self::assertArrayNotHasKey('price', $course);
    }

    public function testPayCourse(): void
    {
        $client = static::getClient();

        $em = self::getEntityManager();
        $userRepository = $em->getRepository(User::class);
        $transactionRepository = $em->getRepository(Transaction::class);

        $transactionsCount = $transactionRepository->count(['type' => 0]);

        // Неавторизован
        $client->request('POST', '/api/v1/courses/python-programming/pay');
        $this->assertResponseCode(401);

        // Пользователь, у которого есть средства
        $this->login($client, self::EMAIL, self::PASSWORD);

        // Курс не найден
        $client->request('POST', '/api/v1/courses/iasufyasgiu-sgagsg/pay');
        $this->assertResponseCode(404);

        // Успешная оплата курса
        $client->request('POST', '/api/v1/courses/python-programming/pay');
        $this->assertResponseCode(200);

        $payInfo = self::parseJsonResponse($client);

        self::assertTrue($payInfo['success']);
        self::assertEquals('rent', $payInfo['course_type']);
        self::assertTrue(abs(
            (new DateTime())->add(new DateInterval('P7D'))->getTimestamp() -
            // 2019-05-20T13:45:11+00:00
            DateTime::createFromFormat(DateTimeInterface::ATOM, $payInfo['expires_at'])->getTimestamp()
        ) < 10);

        // Курс уже арендован
        $client->request('POST', '/api/v1/courses/python-programming/pay');
        $this->assertResponseCode(409);

        // Успешная оплата курса
        $client->request('POST', '/api/v1/courses/building-information-modeling/pay');
        $this->assertResponseCode(200);

        $payInfo = self::parseJsonResponse($client);
        self::assertTrue($payInfo['success']);
        self::assertEquals('buy', $payInfo['course_type']);

        // Курс уже куплен
        $client->request('POST', '/api/v1/courses/building-information-modeling/pay');
        $this->assertResponseCode(409);

        $this->logout($client);

        // Пользователь без средств
        $this->login($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

        // Недостаточно средств
        $client->request('POST', '/api/v1/courses/python-programming/pay');
        $this->assertResponseCode(406);

        $client->request('POST', '/api/v1/courses/building-information-modeling/pay');
        $this->assertResponseCode(406);

        // Оплата не нужна
        $client->request('POST', '/api/v1/courses/interactive-sql-trainer/pay');
        $this->assertResponseCode(200);

        $payInfo = self::parseJsonResponse($client);
        self::assertTrue($payInfo['success']);
        self::assertEquals('free', $payInfo['course_type']);

        // Проверка снятия средств за покупку 2-х курсов
        $user = $userRepository->findOneBy(['email' => self::EMAIL]);
        self::assertEquals(
            abs(round(1000.0 - 10.0 - 20.0, 2)),
            $user->getBalance()
        );

        // Добавилось 2 транзакции
        self::assertEquals($transactionsCount + 2, $transactionRepository->count(['type' => 0]));
    }
}

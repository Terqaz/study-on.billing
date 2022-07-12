<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\CourseType;
use App\Tests\AbstractTest;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;

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

    public function testAddCourse(): void
    {
        $client = static::getClient();
        $this->login($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

        $response = $this->subtestAddCourse($client, 200, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "type" => CourseType::NAMES[CourseType::FREE]
        ]);
        self::assertTrue($response['success']);
        $response = $this->subtestAddCourse($client, 200, [
            "code" => "new-course-2",
            "name" => "Новый курс",
            "type" => CourseType::NAMES[CourseType::RENT],
            "price" => "200"
        ]);
        self::assertTrue($response['success']);
        $response = $this->subtestAddCourse($client, 200, [
            "code" => "new-course-3",
            "name" => "Новый курс",
            "type" => CourseType::NAMES[CourseType::BUY],
            "price" => "300.32"
        ]);
        self::assertTrue($response['success']);

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        self::assertEquals(3, $courseRepository->count(['name' => 'Новый курс']));
    }

    public function testAddCourseFailed(): void
    {
        $client = static::getClient();

        // Без прав админа нет доступа
        $client->request('POST', '/api/v1/courses');
        $this->assertResponseCode(401);

        $this->login($client, self::EMAIL, self::PASSWORD);
        $client->request('POST', '/api/v1/courses');
        $this->assertResponseCode(403);

        $this->login($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

        // Нет кода
        $response = $this->subtestAddCourse($client, 400, [
            "name" => "Новый курс",
            "type" => CourseType::NAMES[CourseType::RENT],
            "price" => "200"
        ]);
        // Нет имени
        $response = $this->subtestAddCourse($client, 400, [
            "code" => "new-course-1",
            "type" => CourseType::NAMES[CourseType::RENT],
            "price" => "200"
        ]);
        // Нет типа
        $response = $this->subtestAddCourse($client, 400, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "price" => "200"
        ]);
        // Неверный тип
        $response = $this->subtestAddCourse($client, 400, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "type" => "sdgdg",
            "price" => "200"
        ]);
        // Нет цены
        $this->subtestAddCourse($client, 400, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "type" => CourseType::NAMES[CourseType::RENT],
        ]);
        $this->subtestAddCourse($client, 400, [
            "code" => "new-course-1",
            "name" => "Новый курс",
            "type" => CourseType::NAMES[CourseType::BUY],
        ]);
        // Курс с таким кодом уже существует
        $this->subtestAddCourse($client, 409, [
            "code" => "python-programming",
            "name" => "Новый курс",
            "type" => "buy",
            "price" => "400"
        ]);
    }

    private function subtestAddCourse(AbstractBrowser $client, $responseCode, $requestBody)
    {
        $client->request(
            'POST',
            '/api/v1/courses',
            [],
            [],
            [],
            json_encode($requestBody, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
        $this->assertResponseCode($responseCode);
        return self::parseJsonResponse($client);
    }

    public function testEditCourse(): void
    {
        $client = static::getClient();
        $this->login($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
    }

    public function testEditCourseFailed(): void
    {
        $client = static::getClient();

        // Без прав админа нет доступа
        $client->request('POST', '/api/v1/courses/interactive-sql-trainer');
        $this->assertResponseCode(401);

        $this->login($client, self::EMAIL, self::PASSWORD);
        $client->request('POST', '/api/v1/courses/interactive-sql-trainer');
        $this->assertResponseCode(403);

        $this->login($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
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

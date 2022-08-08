<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\User;
use App\Service\PaymentService;
use DateInterval;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    private PaymentService $paymentService;

    public function __construct(UserPasswordHasherInterface $passwordHasher, PaymentService $paymentService)
    {
        $this->passwordHasher = $passwordHasher;
        $this->paymentService = $paymentService;
    }

    public function load(ObjectManager $manager): void
    {
        $user1 = (new User())
            ->setEmail('user@example.com')
            ->setRoles(['ROLE_USER']);
        $user1->setPassword($this->passwordHasher->hashPassword(
            $user1,
            'user_password'
        ));
        $manager->persist($user1);

        $user2 = (new User())
            ->setEmail('user2@example.com')
            ->setRoles(['ROLE_USER']);
        $user2->setPassword($this->passwordHasher->hashPassword(
            $user2,
            'user_password'
        ));
        $manager->persist($user2);

        $admin = (new User())
            ->setEmail('admin@example.com')
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setBalance(0.0)
        ;
        $admin->setPassword($this->passwordHasher->hashPassword(
            $admin,
            'admin_password'
        ));
        $manager->persist($admin);

        $coursesByCode = $this->createCourses($manager);

        $this->paymentService->deposit($user1, 99.24);
        $this->paymentService->deposit($user1, 910.76);

        $transaction = $this->paymentService->pay($user1, $coursesByCode['python-programming']);
        $transaction->setCreatedAt((new DateTime())->sub(new DateInterval('P2D')));
        $transaction->setExpiresAt((new DateTime())->sub(new DateInterval('P1D')));

        $transaction = $this->paymentService->pay($user1, $coursesByCode['building-information-modeling']);

        $transaction = $this->paymentService->pay($user1, $coursesByCode['python-programming']);
        $transaction->setExpiresAt((new DateTime())->add(new DateInterval('PT23H')));

        $manager->persist($transaction);

        $this->paymentService->deposit($user2, 1000);
        $transaction = $this->paymentService->pay($user2, $coursesByCode['building-information-modeling']);
        $transaction = $this->paymentService->pay($user2, $coursesByCode['python-programming']);

        $manager->flush();
    }

    /**
     * @param ObjectManager $manager
     * @return array
     */
    public function createCourses(ObjectManager $manager): array
    {
        $coursesByCode = [];

        foreach (self::COURSES_DATA as $courseData) {
            $course = (new Course())
                ->setCode($courseData['code'])
                ->setName($courseData['name'])
                ->setType($courseData['type']);
            if (isset($courseData['price'])) {
                $course->setPrice($courseData['price']);
            }

            $coursesByCode[$courseData['code']] = $course;
            $manager->persist($course);
        }
        return $coursesByCode;
    }

    private const COURSES_DATA = [
        [
            'code' => 'interactive-sql-trainer',
            'name' => 'Интерактивный тренажер по SQL',
            'type' => 0 // free
        ], [
            'code' => 'python-programming',
            'name' => 'Программирование на Python',
            'type' => 1, // rent
            'price' => 10
        ], [
            'code' => 'building-information-modeling',
            'name' => 'Информационное моделирование зданий',
            'type' => 2, // buy
            'price' => 20
        ], [
            'code' => 'some-course',
            'name' => 'Покупаемый в тестах курс',
            'type' => 1, // rent
            'price' => 1
        ]
    ];

    private const TRANSACTIONS_DATA = [
        [
            "type" => 1, // deposit
            "amount" => 99.24
        ], [
            "type" => 1, // deposit
            "amount" => 900.76
        ], [
            "type" => 0, // payment
            "courseCode" => "python-programming",
        ], [
            "type" => 0, // payment
            "courseCode" => "building-information-modeling",
        ]
    ];
}

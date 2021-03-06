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
        $user = (new User())
            ->setEmail('user@example.com')
            ->setRoles(['ROLE_USER'])
        ;
        $user->setPassword($this->passwordHasher->hashPassword(
            $user,
            'user_password'
        ));
        $manager->persist($user);

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

        $this->paymentService->deposit($user, 99.24);
        $this->paymentService->deposit($user, 910.76);
        $transaction = $this->paymentService->pay($user, $coursesByCode['python-programming']);

        $transaction->setCreatedAt((new DateTime())->sub(new DateInterval('P2D')));
        $transaction->setExpiresAt((new DateTime())->sub(new DateInterval('P1D')));

        $manager->persist($transaction);

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
            'name' => '?????????????????????????? ???????????????? ???? SQL',
            'type' => 0 // free
        ], [
            'code' => 'python-programming',
            'name' => '???????????????????????????????? ???? Python',
            'type' => 1, // rent
            'price' => 10
        ], [
            'code' => 'building-information-modeling',
            'name' => '???????????????????????????? ?????????????????????????? ????????????',
            'type' => 2, // buy
            'price' => 20
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

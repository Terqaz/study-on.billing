<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\CourseType;
use App\Enum\TransactionType;
use App\Exception\CourseAlreadyPaidException;
use App\Exception\InsufficientFundsException;
use App\Repository\TransactionRepository;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use InvalidArgumentException;

class PaymentService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function deposit(User $user, float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Вносимая сумма должна быть положительной");
        }

        $this->em->wrapInTransaction(function () use ($user, $amount) {
            $transaction = (new Transaction())
                ->setUserData($user)
                ->setType(TransactionType::DEPOSIT)
                ->setAmount($amount)
                ->setCreatedAt(new DateTime());
            $this->em->persist($transaction);

            $user->setBalance($user->getBalance() + $amount);
            $this->em->persist($user);
        });
    }

    /**
     * @throws InsufficientFundsException
     * @throws CourseAlreadyPaidException
     */
    public function pay(User $user, Course $course): Transaction
    {
        if (round($user->getBalance() - $course->getPrice(), 2) < 0.0) {
            throw new InsufficientFundsException();
        }

        /** @var TransactionRepository $transactionRepository */
        $transactionRepository = $this->em->getRepository(Transaction::class);
        try {
            $count = $transactionRepository->countActiveCourses($user->getId(), $course->getId(), $course->getType());
        } catch (NoResultException|NonUniqueResultException $_) {
        }

        if ($count > 0) {
            throw new CourseAlreadyPaidException();
        }

        $transaction = (new Transaction())
            ->setUserData($user)
            ->setCourse($course)
            ->setType(TransactionType::PAYMENT)
            ->setAmount($course->getPrice())
            ->setCreatedAt(new DateTime());
        if ($course->getType() === CourseType::RENT) {
            $transaction->setExpiresAt((new DateTime())->add(new DateInterval('P7D')));
        }

        $this->em->wrapInTransaction(function () use ($user, $course, $transaction) {
            $this->em->persist($transaction);

            $user->setBalance($user->getBalance() - $course->getPrice());
            $this->em->persist($user);
        });

        return $transaction;
    }
}

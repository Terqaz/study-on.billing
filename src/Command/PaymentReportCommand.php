<?php

namespace App\Command;

use App\Entity\Transaction;
use App\Enum\CourseType;
use App\Repository\TransactionRepository;
use App\Service\Twig;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PaymentReportCommand extends Command
{
    private const COURSE_TYPE_NAMES = [
        CourseType::RENT => 'Аренда',
        CourseType::BUY => 'Покупка',
    ];

    protected static $defaultName = 'payment:report';

    private Twig $twig;
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;

    public function __construct(Twig $twig, EntityManagerInterface $entityManager, MailerInterface $mailer)
    {
        $this->twig = $twig;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var TransactionRepository $transactionRepository */
        $transactionRepository = $this->entityManager->getRepository(Transaction::class);

        $format = 'Y-m-d H:i:s';
        $monthStart = DateTimeImmutable::createFromFormat($format, date('Y-m-01 00:00:00'));
        $monthEnd   = DateTimeImmutable::createFromFormat($format, date('Y-m-t 23:59:59'));
        $courses = $transactionRepository->findPeriodTotalPaid($monthStart, $monthEnd);

        $coursesByEmail = [];
        foreach ($courses as $course) {
            $course['type'] = self::COURSE_TYPE_NAMES[$course['type']];

            $email = $course['email'];
            if (isset($coursesByEmail[$email])) {
                $coursesByEmail[$email][] = $course;
            } else {
                $coursesByEmail[$email] = [$course];
            }
        }

//        dd($coursesByEmail);
        foreach ($coursesByEmail as $email => $userCourses) {
            $totalPaid = 0.0;
            foreach ($userCourses as $userCourse) {
                $totalPaid += $userCourse['course_amount'];
            }

            $html = $this->twig->render(
                'email/payment_report.html.twig',
                [
                    'period' => ['from' => $monthStart, 'to' => $monthEnd],
                    'courses' => $userCourses,
                    'total_paid' => $totalPaid,
                ]
            );

            $email = (new Email())
                ->to($email)
                ->subject('Окончание аренды курсов')
                ->html($html);

            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}

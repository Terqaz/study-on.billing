<?php

namespace App\Command;

use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Service\Twig;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PaymentEndingNotificationCommand extends Command
{
    protected static $defaultName = 'payment:ending:notification';

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
        /** @var CourseRepository $courseRepository */
        $courseRepository = $this->entityManager->getRepository(Course::class);

        $courses = $courseRepository->findExpireInForUsers('P1D');

        $coursesByEmail = [];

        foreach ($courses as $course) {
            $email = $course['email'];
            if (isset($coursesByEmail[$email])) {
                $coursesByEmail[$email][] = $course;
            } else {
                $coursesByEmail[$email] = [$course];
            }
        }

        foreach ($coursesByEmail as $email => $userCourses) {
            $html = $this->twig->render(
                'email/payment_ending_notification.html.twig',
                ['courses' => $userCourses]
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

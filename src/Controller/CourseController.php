<?php

namespace App\Controller;

use App\Entity\Course;
use App\Enum\CourseType;
use App\Exception\CourseAlreadyPaidException;
use App\Exception\InsufficientFundsException;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use App\Service\UserService;
use DateTimeInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CourseController extends AbstractController
{
    /**
     * @Route("/api/v1/courses", name="app_course_index", methods={"GET"})
     * @OA\Get(
     *     description="Get courses data",
     *     tags={"course"},
     *     @OA\Response(
     *          response=200,
     *          description="The courses data",
     *          @OA\JsonContent(
     *              schema="CoursesInfo",
     *              type="array",
     *              @OA\Items(ref=@Model(type=Course::class, groups={"info"}))
     *          )
     *     )
     * )
     */
    public function index(CourseRepository $courseRepository): JsonResponse
    {
        $courses = $courseRepository->findMainInfo();

        foreach ($courses as &$course) {
            $course = self::mapCourseToResponse($course);
        }

        return $this->json($courses);
    }

    /**
     * @Route("/api/v1/courses/{code}", name="app_course_show", methods={"GET"})
     * @OA\Get(
     *     description="Get course data",
     *     tags={"course"},
     *     @OA\Response(
     *          response=200,
     *          description="The course data",
     *          @OA\JsonContent(
     *              ref=@Model(type=Course::class, groups={"info"})
     *          )
     *     ),
     *     @OA\Response(
     *          response=404,
     *          description="Not found",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     )
     * )
     */
    public function show(string $code, CourseRepository $courseRepository): JsonResponse
    {
        $course = $courseRepository->findOneAsArrayByCode($code);
        if (null !== $course) {
            return $this->json(self::mapCourseToResponse($course));
        }

        return $this->json(['error' => 'Course not found'], 404);
    }

    private static function mapCourseToResponse(array $course): array
    {
        if ($course['type'] === CourseType::FREE) {
            unset($course['price']);
        }
        $course['type'] = CourseType::TYPE_NAMES[$course['type']];

        return $course;
    }

    /**
     * @Route("/api/v1/courses/{code}/pay", name="app_course_pay", methods={"POST"})
     * @OA\Post(
     *     description="Pay for the course",
     *     tags={"course"},
     *     @OA\Response(
     *          response=200,
     *          description="Succeded pay info",
     *          @OA\JsonContent(
     *              schema="PayInfo",
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="course_type", type="string"),
     *              @OA\Property(property="expires_at", type="datetime", format="Y-m-d\\TH:i:sP")
     *          )
     *     ),
     *     @OA\Response(
     *          response=406,
     *          description="Not enough funds",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=409,
     *          description="User has already paid for this course",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     )
     * )
     * @Security(name="Bearer")
     */
    public function pay(
        string           $code,
        PaymentService   $paymentService,
        UserService      $userService,
        CourseRepository $courseRepository
    ): JsonResponse {
        try {
            $course = $courseRepository->findOneBy(['code' => htmlspecialchars($code)]);
            if (null === $course) {
                return $this->json(['error' => 'Course not found'], 404);
            }

            $body = [
                'success' => true,
                'course_type' => CourseType::TYPE_NAMES[$course->getType()],
            ];

            // Оплата не нужна
            if ($course->getType() === CourseType::FREE) {
                return $this->json($body);
            }

            $transaction = $paymentService->pay($userService->getFromStorage(), $course);

            if ($course->getType() === CourseType::RENT) {
                $body['expires_at'] = $transaction->getExpiresAt()->format(DateTimeInterface::ATOM);
            }

            return $this->json($body);
        } catch (InsufficientFundsException $e) {
            return $this->json(['error' => 'На вашем счету недостаточно средств'], 406);
        } catch (CourseAlreadyPaidException $e) {
            return $this->json(['error' => 'Курс уже оплачен данным пользователем'], 409);
        }
    }
}

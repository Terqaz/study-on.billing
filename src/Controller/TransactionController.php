<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Repository\TransactionRepository;
use DateTimeInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class TransactionController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;
    private TokenStorageInterface $tokenStorageInterface;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        TokenStorageInterface    $tokenStorageInterface
    ) {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
    }

    /**
     * @Route("/api/v1/transactions", name="app_transaction_index", methods={"GET"})
     * @OA\Get(
     *     description="Get user transactions",
     *     tags={"transaction"},
     *     @OA\Parameter(
     *         name="filter[type]",
     *         in="query",
     *         required=false,
     *         description="Transaction type filter",
     *         @OA\Property(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[course_code]",
     *         in="query",
     *         required=false,
     *         description="Course code filter",
     *         @OA\Property(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[skip_expired]",
     *         in="query",
     *         required=false,
     *         description="Skip expired transactions filter (e.g. rent payment)",
     *         @OA\Property(type="boolean")
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="Transactions info",
     *          @OA\JsonContent(
     *              schema="TransactionsInfo",
     *              type="array",
     *              @OA\Items(
     *                  required={"id", "created_at", "type", "amount"},
     *                  allOf={
     *                      @OA\Schema(ref=@Model(type=Transaction::class, groups={"info"})),
     *                      @OA\Schema(
     *                          type="object",
     *                          @OA\Property(property="course_code", type="string")
     *                      )
     *                  }
     *              )
     *          )
     *     )
     * )
     * @Security(name="Bearer")
     * @throws JWTDecodeFailureException
     */
    public function index(
        Request               $request,
        TransactionRepository $transactionRepository
    ): JsonResponse {
        $token = $this->tokenStorageInterface->getToken();
        if (null === $token) {
            throw new UnauthorizedHttpException('Access token not presented');
        }
        /** @var TokenInterface $decodedJwtToken */
        $decodedJwtToken = $this->jwtManager->decode($token);

        if ($request->query->has('filter')) {
            $filter = array_map('htmlspecialchars', $request->query->all()['filter']);
        } else {
            $filter = [];
        }

        if (isset($filter['type'])) {
            $transactionType = TransactionType::TYPE_CODES[$filter['type']];
        } else {
            $transactionType = null;
        }

        $courseCode = $filter['course_code'] ?? null;

        $skipExpired = isset($filter['skip_expired']) &&
            ($filter['skip_expired'] === 'true' || (int)$filter['skip_expired'] === 1);
        $transactions = $transactionRepository->findByQueryParamsAndUserEmail(
            $decodedJwtToken['username'],
            $transactionType,
            $courseCode,
            $skipExpired
        );

        foreach ($transactions as &$transaction) {
            if ($transaction['type'] === TransactionType::DEPOSIT) {
                unset($transaction['course_code']);
            }
            if (null === $transaction['expires_at']) {
                unset($transaction['expires_at']);
            } else {
                $transaction['expires_at'] = $transaction['expires_at']->format(DateTimeInterface::ATOM);
            }
            $transaction['type'] = TransactionType::TYPE_NAMES[$transaction['type']];
            $transaction['created_at'] = $transaction['created_at']->format(DateTimeInterface::ATOM);
        }

        return $this->json($transactions);
    }
}

<?php

declare(strict_types=1);

namespace TYPO3Incubator\MemberManagement\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3Incubator\MemberManagement\Domain\Repository\MemberRepository;
use TYPO3Incubator\MemberManagement\Service\MembershipService;

#[AsController]
final class BackendMemberController extends ActionController
{
    protected const MEMBER_ACTION_SET_ACTIVE = 'setActive';
    protected const MEMBER_ACTION_SET_INACTIVE = 'setInactive';
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly MemberRepository $memberRepository,
        private readonly MembershipService $membershipService,
    ) {
    }

    protected function initializeAction(): void
    {
        $this->membershipService->setRequest($this->request);
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $members = $this->memberRepository->findConfirmed();
        $itemsPerPage = 20;
        $currentPage = $this->request->hasArgument('currentPageNumber')
            ? (int)$this->request->getArgument('currentPageNumber')
            : 1;
        $maximumLinks = 15;
        $paginator = new QueryResultPaginator($members, $currentPage, $itemsPerPage);
        $pagination = new SlidingWindowPagination(
            $paginator,
            $maximumLinks,
        );
        $moduleTemplate->assignMultiple(
            [
                'pagination' => $pagination,
                'paginator' => $paginator,
            ]
        );

        return $moduleTemplate->renderResponse('Backend/Index');
    }

    public function memberBulkActionAction(array $memberUids = [], string $memberAction = null): ResponseInterface
    {
        if (empty($memberUids) || $memberAction === null) {
            $this->addFlashMessage('No items selected or no action specified.');
            return $this->redirect('index');
        }
        switch ($memberAction) {
            case self::MEMBER_ACTION_SET_ACTIVE:
                $this->membershipService->setMembersActive($memberUids);
                break;
            case self::MEMBER_ACTION_SET_INACTIVE:
                $this->membershipService->setMembersInactive($memberUids);
                break;

        }
        return $this->redirect('index');
    }

}

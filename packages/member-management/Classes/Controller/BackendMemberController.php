<?php

declare(strict_types=1);

namespace TYPO3Incubator\MemberManagement\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3Incubator\MemberManagement\Domain\Repository\MemberRepository;

#[AsController]
final class BackendMemberController extends ActionController
{
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly MemberRepository $memberRepository,
    ) {
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $members = $this->memberRepository->findAll();
        $itemsPerPage = 1;
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
}

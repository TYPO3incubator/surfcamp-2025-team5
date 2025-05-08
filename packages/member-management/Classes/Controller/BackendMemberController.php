<?php

declare(strict_types=1);

namespace TYPO3Incubator\MemberManagement\Controller;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3Incubator\MemberManagement\Domain\Model\MembershipStatus;
use TYPO3Incubator\MemberManagement\Domain\Repository\MemberRepository;
use TYPO3Incubator\MemberManagement\Domain\Repository\MembershipRepository;
use TYPO3Incubator\MemberManagement\Service\MembershipService;
use TYPO3Incubator\MemberManagement\Service\PaymentService;

#[AsController]
final class BackendMemberController extends ActionController
{
    private ModuleTemplate $moduleTemplate;
    private LanguageService $languageService;

    protected const MEMBER_ACTION_SET_ACTIVE = 'setActive';
    protected const MEMBER_ACTION_SET_INACTIVE = 'setInactive';

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly MemberRepository        $memberRepository,
        private readonly MembershipService       $membershipService,
        private readonly PageRenderer            $pageRenderer,
        protected readonly IconFactory           $iconFactory,
        private readonly PaymentService          $paymentService,
        private readonly LanguageServiceFactory  $languageServiceFactory,
        private readonly MembershipRepository    $membershipRepository,
    )
    {
        $this->languageService = $this->languageServiceFactory->createFromUserPreferences(null);
    }

    protected function initializeAction(): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->getDocHeaderButtons($this->moduleTemplate);
        $this->membershipService->setRequest($this->request);
        $this->pageRenderer->loadJavaScriptModule('@vendor/typo3-incubator/member-management/backend.js');
        $this->pageRenderer->addCssFile('EXT:member_management/Resources/Public/Css/backend.css');
    }

    public function indexAction(): ResponseInterface
    {
        // filter fields
        $filters = [];
        $search = '';
        if ($this->request->hasArgument('search')) {
            $search = $this->request->getArgument('search');
            $filters['search'] = $search;
            // you found the beer - CHEERS
            if ($search === '🍺' || $search === '🍻') {
                $filters['search'] = 'Jochen';
            }
        }

        $membershipUid = $this->request->hasArgument('membershipUid')
            ? (int)$this->request->getArgument('membershipUid')
            : 0;

        $membershipStatus = $this->request->hasArgument('membershipStatus')
            ? $this->request->getArgument('membershipStatus')
            : -2;

        // sorting fields
        $fieldMap = [
            'lastName' => 'lastName',
            'membershipTitle' => 'membership.title',
            'membershipStatus' => 'membershipStatus',
            'email' => 'email',
        ];

        $sortField = $this->request->hasArgument('sortField')
            ? $this->request->getArgument('sortField')
            : 'lastName';

        if (!isset($fieldMap[$sortField])) {
            $sortField = 'lastName';
        }

        $realField = $fieldMap[$sortField];

        $sortDirection = QueryInterface::ORDER_ASCENDING;

        if ($this->request->hasArgument('sortDirection')) {
            $sortDirection = $this->request->getArgument('sortDirection');
            $sortDirection = in_array($sortDirection, [QueryInterface::ORDER_ASCENDING, QueryInterface::ORDER_DESCENDING])
                ? $sortDirection
                : QueryInterface::ORDER_ASCENDING;
        }

        $orderings = [
            $realField => $sortDirection,
        ];

        $members = $this->memberRepository->findByFilters($filters, $orderings);
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
        $memberships = $this->membershipRepository->findAll();

        $statusOptions = [
            [
                'value' => -2,
                'label' => 'All',
            ],
            [
                'value' => MembershipStatus::Pending->value,
                'label' => MembershipStatus::Pending->label(),
            ],
            [
                'value' => MembershipStatus::Active->value,
                'label' => MembershipStatus::Active->label(),
            ],
            [
                'value' => MembershipStatus::Inactive->value,
                'label' => MembershipStatus::Inactive->label(),
            ],
        ];

        // get nextSortDirections
        $nextSortDirections = [];
        foreach (array_keys($fieldMap) as $alias) {
            $nextSortDirections[$alias] = ($sortField === $alias && $sortDirection === QueryInterface::ORDER_ASCENDING)
                ? QueryInterface::ORDER_DESCENDING
                : QueryInterface::ORDER_ASCENDING;
        }

        $this->moduleTemplate->assignMultiple(
            [
                'pagination' => $pagination,
                'paginator' => $paginator,
                'search' => $search,
                'membershipUid' => $membershipUid,
                'membershipStatus' => $membershipStatus,
                'memberships' => $memberships,
                'statusOptions' => $statusOptions,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'nextSortDirections' => $nextSortDirections,
            ]
        );

        return $this->moduleTemplate->renderResponse('Backend/Index');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function generateSepaXmlAction(): ResponseInterface
    {
        $this->paymentService->setRequest($this->request);
        $sepaXML = $this->paymentService->generateSepaXml();

        if ($sepaXML) {
            $response = $this->responseFactory->createResponse();

            $response->getBody()->write($sepaXML);

            $site = $this->request->getAttribute('site');
            $siteSettings = $site->getSettings();
            $organizationName = $siteSettings->get('memberManagement.organization.name');
            $filename = "SEPA - $organizationName.xml";

            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if ($origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }

            return $response
                ->withHeader('Content-Type', 'text/xml; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename)
                ->withHeader('Cache-Control', 'private, no-cache, no-store, must-revalidate')
                ->withHeader('Pragma', 'no-cache');
        }

        return $this->moduleTemplate->renderResponse('Backend/Index');
    }

    protected function getDocHeaderButtons(ModuleTemplate $view): void
    {
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();

        $buttonBar->addButton($this->getDocHeaderButtonForGeneratingSepaXml($buttonBar), ButtonBar::BUTTON_POSITION_LEFT, 10);
    }

    private function getDocHeaderButtonForGeneratingSepaXml(ButtonBar $buttonBar): LinkButton
    {
        $href = $this->uriBuilder->reset()->uriFor('generateSepaXml');

        return $buttonBar->makeLinkButton()
            ->setHref($href)
            ->setTitle($this->languageService->sL('LLL:EXT:member_management/Resources/Private/Language/locallang_mod_member.xlf:downloadSepaXmlButton'))
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-download', IconSize::SMALL));
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

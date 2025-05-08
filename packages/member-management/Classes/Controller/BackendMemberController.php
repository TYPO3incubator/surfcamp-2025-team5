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
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3Incubator\MemberManagement\Domain\Repository\MemberRepository;
use TYPO3Incubator\MemberManagement\Service\PaymentService;

#[AsController]
final class BackendMemberController extends ActionController
{
    private ModuleTemplate $moduleTemplate;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly MemberRepository $memberRepository,
        protected readonly IconFactory $iconFactory,
        private readonly PaymentService $paymentService,
    ) {
    }

    protected function initializeAction(): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->getDocHeaderButtons($this->moduleTemplate);
    }

    public function indexAction(): ResponseInterface
    {
        $members = $this->memberRepository->findAll();
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
        $this->moduleTemplate->assignMultiple(
            [
                'pagination' => $pagination,
                'paginator' => $paginator,
            ]
        );

        return $this->moduleTemplate->renderResponse('Backend/Index');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function generateSepaXmlAction(): ResponseInterface {
        $this->paymentService->setRequest($this->request);
        $sepaXML = $this->paymentService->generateSepaXml();

        if (null !== $sepaXML) {
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
            ->setTitle('Download SEPA XML')
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-download', IconSize::SMALL));
    }
}

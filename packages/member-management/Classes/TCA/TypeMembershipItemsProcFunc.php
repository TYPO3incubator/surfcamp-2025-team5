<?php

namespace TYPO3Incubator\MemberManagement\TCA;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3Incubator\MemberManagement\Domain\Model\Membership;
use TYPO3Incubator\MemberManagement\Domain\Repository\MembershipRepository;

final readonly class TypeMembershipItemsProcFunc
{
    public function __construct(
        private MembershipRepository $membershipRepository,
        private SiteFinder $siteFinder
    ){
    }

    public function itemsProcFunc(&$params): void
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($params['effectivePid']);
            $membershipPid = $site->getSettings()->get('memberManagement.storage.membershipsFolderPid');
        } catch (SiteNotFoundException|NotFoundExceptionInterface|ContainerExceptionInterface) {
            return;
        }

        $memberships = $this->membershipRepository->findAllByStorageId($membershipPid)->toArray();
        /** @var Membership $membership */
        foreach ($memberships as $membership) {
            $params['items'][] = [
                'label' => $membership->getTitle(),
                'value' => $membership->getUid(),
            ];
        }
    }
}

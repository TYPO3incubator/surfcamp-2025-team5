<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "member_management".
 *
 * Copyright (C) 2025 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace TYPO3Incubator\MemberManagement\Domain\Repository;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3Incubator\MemberManagement\Domain\Model\Member;
use TYPO3Incubator\MemberManagement\Domain\Model\MembershipStatus;

/**
 * MemberRepository
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 *
 * @extends Repository<Member>
 */
final class MemberRepository extends Repository
{
    public function findOneByHash(string $hash, Site $site, bool $includeDisabled = false): ?Member
    {
        $query = $this->createQueryForSite($site);

        if ($includeDisabled) {
            $querySettings = $query->getQuerySettings();
            $querySettings->setIgnoreEnableFields(true);
            $querySettings->setEnableFieldsToBeIgnored(['disabled']);
        }

        $query->matching(
            $query->equals('createHash', $hash),
        );

        return $query->execute()->getFirst();
    }

    public function findActiveInFolder(int $folderId): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setStoragePageIds([$folderId]);

        $query = $query->matching(
            $query->equals('membership_status', MembershipStatus::Active),
        );

        return $query->execute()->toArray();
    }

    public function findConfirmed(): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);

        $query->matching(
            $query->logicalNot(
                $query->equals('membershipStatus', MembershipStatus::Unconfirmed),
            ),
        );

        return $query->execute();
    }

    /**
     * @return QueryResultInterface<Member>
     */
    public function findBySite(Site $site): QueryResultInterface
    {
        return $this->createQueryForSite($site)->execute();
    }

    private function createQueryForSite(Site $site): QueryInterface
    {
        $storagePid = $site->getSettings()->get('felogin.pid');
        $query = $this->createQuery();
        $query->getQuerySettings()->setStoragePageIds([$storagePid]);

        return $query;
    }
}

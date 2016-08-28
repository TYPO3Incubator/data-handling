<?php
namespace TYPO3\CMS\DataHandling\Extbase\Persistence;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Ramsey\Uuid\UuidInterface;
use TYPO3\CMS\DataHandling\Core\Framework\Domain\Model\ProjectableEntity;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface as SuperRepositoryInterface;

/**
 * The repository for Accounts
 */
interface ProjectionRepository extends SuperRepositoryInterface
{
    /**
     * @param ProjectableEntity $subject
     * @return ProjectableEntity
     */
    public function makeProjectable(ProjectableEntity $subject);

    /**
     * @param UuidInterface $uuid
     * @return null|ProjectableEntity
     */
    public function findByUuid(UuidInterface $uuid);
}

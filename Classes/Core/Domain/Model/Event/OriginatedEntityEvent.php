<?php
namespace TYPO3\CMS\DataHandling\Core\Domain\Model\Event;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\DataHandling\Core\Domain\Object\Meta\EntityReference;
use TYPO3\CMS\DataHandling\Core\Framework\Object\Instantiable;

class OriginatedEntityEvent extends AbstractEvent implements Instantiable
{
    /**
     * @return OriginatedEntityEvent
     */
    public static function instance()
    {
        return GeneralUtility::makeInstance(OriginatedEntityEvent::class);
    }

    /**
     * @param EntityReference $aggregateReference
     * @return OriginatedEntityEvent
     */
    public static function create(EntityReference $aggregateReference)
    {
        $event = static::instance();
        $event->aggregateReference = $aggregateReference;
        return $event;
    }
}
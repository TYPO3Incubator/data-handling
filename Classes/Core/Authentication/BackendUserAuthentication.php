<?php
namespace TYPO3\CMS\DataHandling\Core\Authentication;

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

use TYPO3\CMS\DataHandling\DataHandling\Domain\Model\Common\ProjectionContext;

class BackendUserAuthentication extends \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
{
    public function workspaceInit()
    {
        parent::workspaceInit();

        $projectionContext = ProjectionContext::provide();
        $projectionContext->setWorkspaceId($this->workspace);
        $projectionContext->lock();

        $projectionContext->enforceLocalStorage();
    }
}

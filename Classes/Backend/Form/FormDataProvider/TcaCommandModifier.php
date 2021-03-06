<?php
namespace TYPO3\CMS\DataHandling\Backend\Form\FormDataProvider;

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

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\DataHandling\Core\Domain\Model\Base\TcaCommand\TcaCommand;
use TYPO3\CMS\DataHandling\Core\Domain\Model\Base\TcaCommand\TcaCommandEntityBehavior;
use TYPO3\CMS\DataHandling\Core\Domain\Model\Base\TcaCommand\TcaCommandManager;
use TYPO3\CMS\EventSourcing\Core\Domain\Model\Meta\ActiveRelation;
use TYPO3\CMS\EventSourcing\Core\Domain\Model\Meta\RelationMap;

/**
 * Modifies TCA settings depending on current state
 * and TcaCommand definitions for projections and relations.
 */
class TcaCommandModifier implements FormDataProviderInterface
{
    /**
     * @var string
     */
    private $tableName;

    /**
     * @var TcaCommand
     */
    private $tcaCommand;

    /**
     * @var array
     */
    private $formEngineResult;

    /**
     * @param array $result
     * @return array
     */
    public function addData(array $result)
    {
        $this->tableName = $result['tableName'];

        $tcaCommandManager = TcaCommandManager::provide();
        if (!$tcaCommandManager->has($this->tableName)) {
            return $result;
        }

        $this->formEngineResult = $result;
        $this->tcaCommand = $tcaCommandManager->for($this->tableName);

        if ($result['command'] === 'new') {
            $this->handleNewAction();
        }
        if ($result['command'] === 'edit') {
            $this->handleEditAction();
        }

        return $this->formEngineResult;
    }

    private function handleNewAction()
    {
        $behavior = $this->tcaCommand->onCreate();
        $this->handleBehavior($behavior);
    }

    private function handleEditAction()
    {
        $behavior = $this->tcaCommand->onModify();
        $this->handleBehavior($behavior);
    }

    /**
     * @param TcaCommandEntityBehavior $behavior
     */
    private function handleBehavior(TcaCommandEntityBehavior $behavior)
    {
        if (!$behavior->isAllowed()) {
            $this->definedReadOnlyFields(
                array_keys($this->formEngineResult['processedTca']['columns'])
            );
            return;
        }

        $missingNames = array_diff(
            array_keys($this->formEngineResult['processedTca']['columns']),
            array_keys($behavior->getProperties())
        );

        $this->definedReadOnlyFields($missingNames);

        foreach ($behavior->getProperties() as $name => $instruction) {
            if ($instruction instanceof \Closure) {
                $this->assignValue($name, $instruction());
            } elseif (is_callable($instruction)) {
                $this->assignValue($name, call_user_func($instruction));
            }
        }

        $properties = RelationMap::provide()->getSchema($this->tableName)->getProperties();
        foreach ($properties as $property) {
            foreach ($property->getActiveRelations() as $relation) {
                $this->handleRelation($relation, $behavior);
            }
        }
    }

    /**
     * @param ActiveRelation $relation
     * @param TcaCommandEntityBehavior $behavior
     */
    private function handleRelation(ActiveRelation $relation, TcaCommandEntityBehavior $behavior)
    {
        $propertyName = $relation->getProperty()->getName();
        $type = $this->formEngineResult['processedTca']['columns'][$propertyName]['config']['type'];

        // @todo group & select are missing here

        if ($type === 'inline') {
            if (!$behavior->hasRelation($propertyName)) {
                $this->formEngineResult['processedTca']['columns'][$propertyName]['config']['appearance']['enabledControls'] = [];
                return;
            }

            $relationBehavior = $behavior->forRelation($propertyName);
            $referenceTableBehavior = TcaCommandManager::provide()->for($relation->getTo()->getName());

            $enabledControls = [];
            $currentEnableControls = null;
            if (isset($this->formEngineResult['processedTca']['columns'][$propertyName]['config']['appearance']['enabledControls'])) {
                $currentEnableControls = $this->formEngineResult['processedTca']['columns'][$propertyName]['config']['appearance']['enabledControls'];
            }

            // @todo "localize" is missing here

            if ($relationBehavior->isAttachAllowed() && $referenceTableBehavior->onCreate()->isAllowed()) {
                $enabledControls['new'] = true;
            }
            if ($relationBehavior->isRemoveAllowed() && $referenceTableBehavior->onDelete()->isAllowed()) {
                $enabledControls['delete'] = true;
            }
            if ($relationBehavior->isOrderAllowed()) {
                $enabledControls['sort'] = true;
                $enabledControls['dragdrop'] = true;
            }
            if ($referenceTableBehavior->onDisable()->isAllowed()) {
                $enabledControls['delete'] = true;
            }

            if ($currentEnableControls !== null) {
                $enabledControls = array_intersect_assoc(
                    $enabledControls,
                    $currentEnableControls
                );
            }

            $this->formEngineResult['processedTca']['columns'][$propertyName]['config']['appearance']['enabledControls'] = $enabledControls;
        }
    }

    /**
     * @param array $names
     */
    private function definedReadOnlyFields(array $names)
    {
        foreach ($names as $name) {
            $this->formEngineResult['processedTca']['columns'][$name]['config']['readOnly'] = true;
        }
    }

    /**
     * @param string $name
     * @param string $defaultValue
     */
    private function assignValue(string $name, string $defaultValue)
    {
        $this->formEngineResult['databaseRow'][$name] = $defaultValue;
    }
}

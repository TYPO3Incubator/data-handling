<?php
namespace TYPO3\CMS\DataHandling\Core\DataHandling\Resolver;

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
use TYPO3\CMS\DataHandling\Core\MetaModel\Map;
use TYPO3\CMS\DataHandling\Core\MetaModel\PassiveRelation;
use TYPO3\CMS\DataHandling\Core\Service\MetaModelService;
use TYPO3\CMS\DataHandling\Domain\Object\Record;

class AggregateResolver
{
    /**
     * @return AggregateResolver
     */
    public static function instance()
    {
        return GeneralUtility::makeInstance(AggregateResolver::class);
    }

    protected $proxy;

    /**
     * @var Record\Change[]
     */
    protected $subjects;

    /**
     * @var Record\Aggregate[]
     */
    protected $aggregates;

    /**
     * @var Record\Aggregate[]
     */
    protected $rootAggregates;

    public function setProxy($proxy): AggregateResolver
    {
        $this->proxy = $proxy;
        return $this;
    }

    public function getProxy()
    {
        return $this->proxy;
    }

    public function setSubjects(array $subjects): AggregateResolver
    {
        $this->subjects = $subjects;
        return $this;
    }

    /**
     * @return Record\Change[]
     */
    public function resolve(): array
    {
        if (!isset($this->rootAggregates)) {
            $this->build();
        }
        return $this->sequence();
    }

    /**
     * @param Record\Aggregate $aggregate
     * @return Record\Aggregate[]
     */
    protected function getRootAggregates(Record\Aggregate $aggregate): array
    {
        /** @var Record\Aggregate[] $activeAggregates */
        $rootAggregates = [];
        /** @var Record\Aggregate[] $activeAggregates */
        $activeAggregates = [];

        $metaModelProperties = Map::instance()->getSchema($aggregate->getState()->getReference()->getName());

        foreach ($metaModelProperties->getProperties() as $metaModelProperty) {
            $passiveRelations = $metaModelProperty->getPassiveRelations();
            if (empty($passiveRelations)) {
                continue;
            }

            $stateValue = $aggregate->getState()->getValue($metaModelProperty->getName());
            $stateRelations = $aggregate->getState()->getPropertyRelations($metaModelProperty->getName());

            // e.g. inline-child having foreign_field value defined
            // -> fetch originator by using value (uid/uuid) as parent entity selector
            // -> most probably fetch originator from database
            if ($stateValue !== null) {
                // @todo Implement look-up
            // e.g. inline-child using foreign_field as e.g. select type
            // -> fetch originator by using reference as parent entity selector
            // -> most probably fetch originator from database
            } elseif (count($stateRelations) > 0) {
                // @todo Implement look-up
            // otherwise check root AggregateResolver for reference pointing to us
            } else {
                foreach ($passiveRelations as $passiveRelation) {
                    $activeAggregates = $activeAggregates + $this->findActiveRelationAggregates($passiveRelation, $aggregate);
                }
            }
        }

        foreach ($activeAggregates as $activeAggregate) {
            $activeAggregate->addNestedAggregate($aggregate);
            $rootAggregates = $rootAggregates + $this->getRootAggregates($activeAggregate);
        }

        if (empty($rootAggregates)) {
            $rootAggregates[] = $aggregate;
        }

        return $rootAggregates;
    }

    protected function build()
    {
        $this->aggregates = [];
        $this->rootAggregates = [];

        foreach ($this->subjects as $change) {
            // @todo Proxy
            $this->aggregates[] = Record\Aggregate::instance()->setState($change->getTargetState());
        }

        foreach ($this->aggregates as $aggregate) {
            foreach ($this->getRootAggregates($aggregate) as $rootAggregate) {
                if (!in_array($rootAggregate, $this->rootAggregates, true)) {
                    $this->rootAggregates[] = $rootAggregate;
                }
            }
        }
    }

    /**
     * @return Record\Change[]
     */
    protected function sequence(): array
    {
        $sequence = [];
        foreach ($this->rootAggregates as $rootAggregate) {
            $sequence[] = $this->resolveSubject($rootAggregate);
            foreach ($rootAggregate->getDeepNestedAggregates() as $nestedAggregate) {
                $subject = $this->resolveSubject($nestedAggregate);
                if (!in_array($subject, $sequence)) {
                    $sequence[] = $subject;
                }
            }
        }
        return $sequence;
    }

    /**
     * @param Record\Aggregate $aggregate
     * @return Record\Change
     */
    protected function resolveSubject(Record\Aggregate $aggregate): Record\Change
    {
        foreach ($this->subjects as $subject) {
            // @todo Proxy
            if ($subject->getTargetState() !== $aggregate->getState()) {
                continue;
            }
            return $subject;
        }
        throw new \RuntimeException('Subject cannot be resolved', 1470672893);
    }

    /**
     * @param PassiveRelation $passiveRelation
     * @param Record\Aggregate $needle
     * @return Record\Aggregate[]
     */
    protected function findActiveRelationAggregates(PassiveRelation $passiveRelation, Record\Aggregate $needle): array
    {
        $activeAggregates = [];
        $activeProperty = $passiveRelation->getFrom();

        foreach ($this->aggregates as $aggregate) {
            $state = $aggregate->getState();
            // skip aggregates that are do not issue the requested passive relation
            if ($state->getReference()->getName() !== $activeProperty->getSchema()->getName()) {
                continue;
            }

            $aggregateRelations = $state->getPropertyRelations($activeProperty->getName());
            foreach ($aggregateRelations as $aggregateRelation) {
                // skip aggregate relations that do not issue the requested passive relation
                if (!$aggregateRelation->getEntityReference()->equals($needle->getState()->getReference())) {
                    continue;
                }
                $activeAggregates[] = $aggregate;
            }
        }

        return $activeAggregates;
    }
}
<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\EventListener;

use Mautic\LeadBundle\Event\SegmentOperatorQueryBuilderEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Segment\Query\Expression\CompositeExpression;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SegmentOperatorQuerySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LIST_FILTERS_OPERATOR_QUERYBUILDER_ON_GENERATE => [
                ['onEmptyOperator', 0],
                ['onNotEmptyOperator', 0],
                ['onNegativeOperators', 0],
                ['onMultiselectOperators', 0],
                ['onDefaultOperators', 0],
            ],
        ];
    }

    public function onEmptyOperator(SegmentOperatorQueryBuilderEvent $event): void
    {
        if (!$event->operatorIsOneOf('empty')) {
            return;
        }

        $event->addExpression(
            new CompositeExpression(
                CompositeExpression::TYPE_OR,
                [
                    $event->getQueryBuilder()->expr()->isNull('l.'.$event->getFilter()->getField()),
                    $event->getQueryBuilder()->expr()->eq(
                        'l.'.$event->getFilter()->getField(),
                        $event->getQueryBuilder()->expr()->literal('')
                    ),
                ]
            )
        );

        $event->stopPropagation();
    }

    public function onNotEmptyOperator(SegmentOperatorQueryBuilderEvent $event): void
    {
        if (!$event->operatorIsOneOf('notEmpty')) {
            return;
        }

        $event->addExpression(
            new CompositeExpression(
                CompositeExpression::TYPE_AND,
                [
                    $event->getQueryBuilder()->expr()->isNotNull('l.'.$event->getFilter()->getField()),
                    $event->getQueryBuilder()->expr()->neq(
                        'l.'.$event->getFilter()->getField(),
                        $event->getQueryBuilder()->expr()->literal('')
                    ),
                ]
            )
        );

        $event->stopPropagation();
    }

    public function onNegativeOperators(SegmentOperatorQueryBuilderEvent $event): void
    {
        if (!$event->operatorIsOneOf(
            'neq',
            'notLike',
            'notBetween', //Used only for date with week combination (NOT EQUAL [this week, next week, last week])
            'notIn'
        )) {
            return;
        }

        $event->addExpression(
            $event->getQueryBuilder()->expr()->orX(
                $event->getQueryBuilder()->expr()->isNull('l.'.$event->getFilter()->getField()),
                $event->getQueryBuilder()->expr()->{$event->getFilter()->getOperator()}(
                    'l.'.$event->getFilter()->getField(),
                    $event->getParameterHolder()
                )
            )
        );

        $event->stopPropagation();
    }

    public function onMultiselectOperators(SegmentOperatorQueryBuilderEvent $event): void
    {
        if (!$event->operatorIsOneOf('multiselect', '!multiselect')) {
            return;
        }

        $operator    = 'multiselect' === $event->getFilter()->getOperator() ? 'regexp' : 'notRegexp';
        $expressions = [];

        foreach ($event->getParameterHolder() as $parameter) {
            $expressions[] = $event->getQueryBuilder()->expr()->$operator('l.'.$event->getFilter()->getField(), $parameter);
        }

        $event->addExpression($event->getQueryBuilder()->expr()->andX($expressions));
        $event->stopPropagation();
    }

    public function onDefaultOperators(SegmentOperatorQueryBuilderEvent $event): void
    {
        if (!$event->operatorIsOneOf(
            'startsWith',
            'endsWith',
            'gt',
            'eq',
            'gte',
            'like',
            'lt',
            'lte',
            'in',
            'between', //Used only for date with week combination (EQUAL [this week, next week, last week])
            'regexp',
            'notRegexp' //Different behaviour from 'notLike' because of BC (do not use condition for NULL). Could be changed in Mautic 3.
        )) {
            return;
        }

        $event->addExpression(
            $event->getQueryBuilder()->expr()->{$event->getFilter()->getOperator()}(
                'l.'.$event->getFilter()->getField(),
                $event->getParameterHolder()
            )
        );

        $event->stopPropagation();
    }
}

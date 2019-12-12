<?php

/**
 * Spiral Framework. PHP Data Grid
 *
 * @license MIT
 * @author  Anton Tsitou (Wolfy-J)
 * @author  Valentin Vintsukevich (vvval)
 */

declare(strict_types=1);

namespace Spiral\DataGrid\Writer;

use Cycle\ORM\Select;
use Spiral\Database\Injection\Parameter;
use Spiral\Database\Query\SelectQuery;
use Spiral\DataGrid\Compiler;
use Spiral\DataGrid\Exception\CompilerException;
use Spiral\DataGrid\Specification;
use Spiral\DataGrid\SpecificationInterface;
use Spiral\DataGrid\WriterInterface;

/**
 * Provides the ability to write into spiral/database SelectQuery and cycle/orm Select.
 */
class QueryWriter implements WriterInterface
{
    // Expression mapping
    private const COMPARE_OPERATORS = [
        Specification\Filter\Lte::class       => '<=',
        Specification\Filter\Lt::class        => '<',
        Specification\Filter\Equals::class    => '=',
        Specification\Filter\NotEquals::class => '!=',
        Specification\Filter\Gt::class        => '>',
        Specification\Filter\Gte::class       => '>=',
    ];
    private const ARRAY_OPERATORS   = [
        Specification\Filter\InArray::class    => 'IN',
        Specification\Filter\NotInArray::class => 'NOT IN',
    ];

    // Sorter directions mapping
    private const SORTER_DIRECTIONS = [
        Specification\Sorter\AscSorter::class  => 'ASC',
        Specification\Sorter\DescSorter::class => 'DESC',
    ];

    /**
     * @inheritDoc
     */
    public function write($source, SpecificationInterface $specification, Compiler $compiler)
    {
        if (!$this->targetAcceptable($source)) {
            return null;
        }

        if ($specification instanceof Specification\SequenceInterface) {
            return $compiler->compile($source, ...$specification->getSpecifications());
        }

        if ($specification instanceof Specification\FilterInterface) {
            return $this->writeFilter($source, $specification, $compiler);
        }

        if ($specification instanceof Specification\SorterInterface) {
            return $this->writeSorter($source, $specification, $compiler);
        }

        if ($specification instanceof Specification\Pagination\Limit) {
            return $source->limit($specification->getValue());
        }

        if ($specification instanceof Specification\Pagination\Offset) {
            return $source->offset($specification->getValue());
        }

        return null;
    }

    /**
     * @param SelectQuery|Select            $source
     * @param Specification\FilterInterface $filter
     * @param Compiler                      $compiler
     * @return mixed
     */
    protected function writeFilter($source, Specification\FilterInterface $filter, Compiler $compiler)
    {
        if ($filter instanceof Specification\Filter\All || $filter instanceof Specification\Filter\Map) {
            return $source->where(static function () use ($compiler, $filter, $source): void {
                $compiler->compile($source, ...$filter->getFilters());
            });
        }

        if ($filter instanceof Specification\Filter\Any) {
            return $source->where(static function () use ($compiler, $filter, $source): void {
                foreach ($filter->getFilters() as $subFilter) {
                    $source->orWhere(static function () use ($compiler, $subFilter, $source): void {
                        $compiler->compile($source, $subFilter);
                    });
                }
            });
        }

        if ($filter instanceof Specification\Filter\Like) {
            return $source->where(
                $filter->getExpression(),
                'LIKE',
                sprintf($filter->getPattern(), $this->fetchValue($filter->getValue()))
            );
        }

        if ($filter instanceof Specification\Filter\InArray || $filter instanceof Specification\Filter\NotInArray) {
            return $source->where(
                $filter->getExpression(),
                self::ARRAY_OPERATORS[get_class($filter)],
                new Parameter($this->fetchValue($filter->getValue()))
            );
        }

        if (isset(self::COMPARE_OPERATORS[get_class($filter)])) {
            return $source->where(
                $filter->getExpression(),
                self::COMPARE_OPERATORS[get_class($filter)],
                $this->fetchValue($filter->getValue())
            );
        }

        return null;
    }

    /**
     * @param SelectQuery|Select            $source
     * @param Specification\SorterInterface $sorter
     * @param Compiler                      $compiler
     * @return mixed
     */
    protected function writeSorter($source, Specification\SorterInterface $sorter, Compiler $compiler)
    {
        if ($sorter instanceof Specification\Sorter\SorterSet) {
            foreach ($sorter->getSorters() as $subSorter) {
                $source = $compiler->compile($source, $subSorter);
            }

            return $source;
        }

        if (
            $sorter instanceof Specification\Sorter\AscSorter
            || $sorter instanceof Specification\Sorter\DescSorter
        ) {
            $direction = static::SORTER_DIRECTIONS[get_class($sorter)];
            foreach ($sorter->getExpressions() as $expression) {
                $source = $source->orderBy($expression, $direction);
            }

            return $source;
        }

        return null;
    }

    /**
     * Fetch and assert that filter value is not expecting any user input.
     *
     * @param Specification\ValueInterface|mixed $value
     * @return mixed
     */
    protected function fetchValue($value)
    {
        if ($value instanceof Specification\ValueInterface) {
            throw new CompilerException('Value expects user input, none given');
        }

        return $value;
    }

    /**
     * @param mixed $target
     * @return bool
     */
    protected function targetAcceptable($target): bool
    {
        if (class_exists(SelectQuery::class) && $target instanceof SelectQuery) {
            return true;
        }

        if (class_exists(Select::class) && $target instanceof Select) {
            return true;
        }

        return false;
    }
}

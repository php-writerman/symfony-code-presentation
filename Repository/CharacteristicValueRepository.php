<?php

namespace PhpWriterman\ProductBundle\Repository;


use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PhpWriterman\ProductBundle\Entity\ProductModelInterface;

/**
 * Class CharacteristicValueRepository
 *
 * @package PhpWriterman\ProductBundle\Repository
 */
class CharacteristicValueRepository extends EntityRepository
{
    /**
     * @param $filters
     *
     * @return array
     */
    public function findAllWithModelsCount($filters = [])
    {
        /** @var QueryBuilder $modelsBuilder */
        $modelsBuilder = $this->_em->getRepository(ProductModelInterface::class)
            ->getFilteredBuilder(['alias' => 'models', 'subQueryForValuesFlag' => true], $filters);

        $modelsBuilder->select('COUNT(models)');

        $builder = $this->createQueryBuilder('value')
            ->addSelect('translations')
            ->addSelect('characteristic')
            ->join('value.translations', 'translations')
            ->join('value.characteristic', 'characteristic')
            ->orderBy('value.position', 'ASC')
            ->addSelect("({$modelsBuilder->getDQL()}) modelsCount");

        if (isset($filters['inMenuFlag'])) {
            $builder->where('characteristic.inMenuFlag = :inMenuFlag')
                ->setParameter('inMenuFlag', $filters['inMenuFlag']);
        }

        foreach ($modelsBuilder->getParameters() as $parameter) {
            $builder->setParameter($parameter->getName(), $parameter->getValue());
        }

        return $builder->getQuery()->getResult();
    }

    /**
     * Apply filter by characteristic values ids and by category
     *
     * @param QueryBuilder $builder
     * @param $filters
     * @param array $params
     */
    public function applyFilterCondition(QueryBuilder $builder, $filters, $params = [])
    {
        $modelsAlias = $params['modelsAlias'] ?? 'models';
        $characteristicValuesAlias = $params['valuesAlias'] ?? 'characteristicValues';

        // Add values filtering only when filter data available
        if ($this->isNeedToApplyValuesFilter($filters)) {

            $whereInBuilder = $this
                ->createAvailableValuesBuilder($filters, '2')
                ->select('availableValues2.id');
            $builder->andWhere("$characteristicValuesAlias.id IN ({$whereInBuilder->getDQL()})");

            $havingBuilder = $this->createAvailableValuesBuilder($filters)
                ->select("COUNT(DISTINCT availableValues.characteristic)");
            $builder->groupBy("$modelsAlias.id")
                ->having("COUNT(DISTINCT $characteristicValuesAlias.characteristic) >= ({$havingBuilder->getDQL()})");

            $builder->setParameters($havingBuilder->getParameters());
            $builder->setParameters($whereInBuilder->getParameters());
        }
    }

    /**
     * Apply filter by product
     *
     * @param QueryBuilder $builder
     * @param $product
     * @param array $params
     */
    public function applyFilterByProductCondition(QueryBuilder $builder, $product, $params = [])
    {
        $characteristicValuesAlias = $params['valuesAlias'] ?? 'characteristicValues';
        $categoriesAlias = $params['categoriesAlias'] ?? 'categories';

        $builder
            ->andWhere("$characteristicValuesAlias.id IN (
                    SELECT availableValues.id FROM \\PhpWriterman\\ProductBundle\\Entity\\CharacteristicValueInterface availableValues
                        LEFT JOIN availableValues.productModels availableProductModels
                        WHERE availableProductModels.product = :product
                        AND availableValues.characteristic = $characteristicValuesAlias.characteristic)"
            )
            ->groupBy("$categoriesAlias.id")
            ->having("COUNT(DISTINCT $characteristicValuesAlias.characteristic) >= (
                    SELECT COUNT(DISTINCT availableValues2.characteristic)
                        FROM \\PhpWriterman\\ProductBundle\\Entity\\CharacteristicValueInterface availableValues2
                        LEFT JOIN availableValues2.productModels availableProductModels2
                        WHERE availableProductModels2.product = :product
                        AND availableValues2.characteristic = $characteristicValuesAlias.characteristic)"
            )
            ->setParameter('product', $product);
    }

    /**
     * Apply filter by characteristic values ids, category and value.id from parent query
     *
     * @param QueryBuilder $builder
     * @param $filters
     * @param array $params
     */
    public function applyFilterWithCurrentValueCondition(QueryBuilder $builder, $filters, $params = [])
    {
        $modelsAlias = $params['modelsAlias'] ?? 'models';
        $characteristicValuesAlias = $params['valuesAlias'] ?? 'characteristicValues';

        // Add values filtering only when filter data available
        if ($this->isNeedToApplyValuesFilter($filters)) {
            $whereInBuilder = $this
                ->createAvailableValuesWithCurrentValueBuilder($filters, '2')
                ->select('availableValues2.id');
            $builder->andWhere("$characteristicValuesAlias.id IN ({$whereInBuilder->getDQL()})");

            $havingBuilder = $this->createAvailableValuesWithCurrentValueBuilder($filters)
                ->select("COUNT(DISTINCT availableValues.characteristic)");
            $builder->groupBy("$modelsAlias.id")
                ->having("COUNT(DISTINCT $characteristicValuesAlias.characteristic) >= ({$havingBuilder->getDQL()})");

            $builder->setParameters($havingBuilder->getParameters());
            $builder->setParameters($whereInBuilder->getParameters());
        }
    }

    /**
     * Apply filter by category and value.id from parent query
     *
     * @param array $filters
     * @param $aliasPostfix
     *
     * @return QueryBuilder
     */
    public function createAvailableValuesBuilder($filters, $aliasPostfix = '')
    {
        $ids = $filters['characteristicsValuesIds'] ?? [];
        $category = $filters['category'] ?? null;
        $filter = $filters['filter'] ?? null;
        $alias = "availableValues$aliasPostfix";
        $catAlias = "availableCat$aliasPostfix";
        $filterAlias = "availableFilter$aliasPostfix";

        $builder = $this->createQueryBuilder($alias);

        if ($ids) {
            $builder
                ->where("$alias.id IN (:characteristicValuesIds)")
                ->setParameter('characteristicValuesIds', $ids);
        }

        if ($category) {
            $builder
                ->leftJoin("$alias.categories", $catAlias)
                ->orWhere("$catAlias.id = :category")
                ->setParameter('category', $category);
        }

        if ($filter) {
            $builder
                ->leftJoin("$alias.filters", $filterAlias)
                ->orWhere("$filterAlias.id = :filter")
                ->setParameter('filter', $filter);
        }

        return $builder;
    }

    /**
     * Apply filter by category and value.id from parent query
     *
     * @param array $filters
     * @param $aliasPostfix
     * @param $params
     *
     * @return QueryBuilder
     */
    public function createAvailableValuesWithCurrentValueBuilder($filters, $aliasPostfix = '', $params = [])
    {
        $ids = $filters['characteristicsValuesIds'] ?? [];
        $category = $filters['category'] ?? null;
        $filter = $filters['filter'] ?? null;
        $alias = "availableValues$aliasPostfix";
        $catAlias = "availableCat$aliasPostfix";
        $currentValueAlias = $params['currentValueAlias'] ?? 'value';
        $filterAlias = "availableFilter$aliasPostfix";

        $builder = $this->createQueryBuilder($alias)
            ->where("$alias.id = $currentValueAlias.id");

        if ($ids) {
            $builder
                ->orWhere("$alias.id IN (:characteristicValuesIds) ")
                ->setParameter('characteristicValuesIds', $ids);
        }

        if ($category) {
            $builder
                ->leftJoin("$alias.categories", $catAlias)
                ->orWhere("$catAlias.id = :category")
                ->setParameter('category', $category);
        }

        if ($filter) {
            $builder
                ->leftJoin("$alias.filters", $filterAlias)
                ->orWhere("$filterAlias.id = :filter")
                ->setParameter('filter', $filter);
        }

        return $builder;
    }

    /**
     * @param $filters
     * @return bool
     */
    protected function isNeedToApplyValuesFilter($filters)
    {
        return isset($filters['category']) || isset($filters['filter']) || isset($filters['characteristicsValuesIds']);
    }
}

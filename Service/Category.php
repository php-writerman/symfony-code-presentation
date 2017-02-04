<?php

namespace PhpWriterman\ProductBundle\Service;


use Doctrine\ORM\EntityManager;
use PhpWriterman\ProductBundle\Entity\CategoryInterface;
use PhpWriterman\ProductBundle\Entity\Product;
use PhpWriterman\ProductBundle\Entity\Category as CategoryEntity;

/**
 * Class Category
 *
 * @package PhpWriterman\ProductBundle\Service
 */
class Category
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Return categories with filled nested parent-child relationships
     *
     * @return bool|mixed
     */
    public function getTopLevelWithChildren()
    {
        $categories = $this->em->getRepository(CategoryInterface::class)->findActive();

        return $this->getTree($categories);
    }

    /**
     * Return categories with filled nested parent-child relationships
     *
     * @param $categories
     * @return array
     */
    public function getTree($categories)
    {
        $prop = $this->em->getClassMetadata(CategoryInterface::class)->reflFields['children'];

        // Prevent doctrine children preload
        foreach ($categories as &$entity) {
            $prop->getValue($entity)->setInitialized(true);
        }

        // Map relationships
        foreach ($categories as $category) {
            if ($category->getParent()) {
                foreach ($categories as $searchedCategory) {
                    if ($searchedCategory->getId() == $category->getParent()->getId()) {
                        $searchedCategory->addChildren($category);
                    }
                }
            }
        }

        // Return top level categories
        return array_filter($categories, function ($category) {
            return !$category->getParent();
        });
    }

    /**
     * @param CategoryEntity $category
     * @param $alias
     *
     * @return bool
     */
    public function isCurrentCategoryAlias(CategoryEntity $category, $alias)
    {
        if ($category->getAlias() == $alias) {
            return true;
        }

        foreach ($category->getChildren() as $child) {
            if ($this->isCurrentCategoryAlias($child, $alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getProductCategories(Product $product)
    {
        $categories = [];

        do {
            $parent = end($categories) ?: null;
            $category = $this->em->getRepository(CategoryInterface::class)->findCategoryByProduct($product, $parent);
            if ($category) {
                $categories[] = $category;
            }
        } while ($category);

        return $categories;
    }

    /**
     * @param CategoryEntity $category
     * @return array
     */
    public function getCategoriesWithParents(CategoryEntity $category)
    {
        $categories[] = $category;

        while ($category->getParent()) {
            $categories[] = $category->getParent();
            $category = $category->getParent();
        }

        return array_reverse($categories);
    }
}

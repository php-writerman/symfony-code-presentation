<?php

namespace PhpWriterman\ImageBundle\ORM;


use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use PhpWriterman\ImageBundle\Entity\ImageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Imageable Doctrine2 subscriber.
 *
 * Provides mapping for imageable entities and their images.
 *
 * @package PhpWriterman\ImageBundle\ORM
 */
class ImageableSubscriber implements EventSubscriber
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $imageableTrait;

    /**
     * @var CamelCaseToSnakeCaseNameConverter
     */
    protected $camelToSnakeCaseConverter;

    /**
     * ImageableSubscriber constructor.
     *
     * @param ContainerInterface $container
     * @param $imageableTrait
     */
    public function __construct(ContainerInterface $container, $imageableTrait)
    {
        $this->container = $container;
        $this->imageableTrait = $imageableTrait;
        $this->camelToSnakeCaseConverter = new CamelCaseToSnakeCaseNameConverter();
    }

    /**
     * Adds mapping to the imageable and images.
     *
     * @param LoadClassMetadataEventArgs $eventArgs The event arguments
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $classMetadata = $eventArgs->getClassMetadata();

        if ($classMetadata->isMappedSuperclass || null === $classMetadata->reflClass) {
            return;
        }

        if ($this->isImage($classMetadata)) {
            $this->mapImage($classMetadata);
        }

        if ($this->isImageable($classMetadata)) {
            $this->mapImageable($classMetadata);
        }
    }

    /**
     * Returns hash of events, that this subscriber is bound to.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata
        ];
    }

    /**
     * @param ClassMetadata $classMetadata
     */
    private function mapImageable(ClassMetadata $classMetadata)
    {
        if (!$classMetadata->hasAssociation('images')) {
            $classMetadata->mapManyToMany([
                'fieldName' => 'images',
                'inversedBy' => 'imageables',
                'cascade' => ['persist', 'merge'],
                'fetch' => ClassMetadataInfo::FETCH_LAZY,
                'targetEntity' => $classMetadata->getReflectionClass()->getMethod('getImageEntityClass')->invoke(null)
            ]);
        }
    }

    /**
     * @param ClassMetadata $classMetadata
     */
    private function mapImage(ClassMetadata $classMetadata)
    {
        if (!$classMetadata->hasAssociation('imageables')) {
            if ($classMetadata->getReflectionClass()->isAbstract()) {
                // Generate DiscriminatorMap
                foreach ($this->container->getParameter('images.imageables_classes') as $className) {
                    $classMetadata->setDiscriminatorMap([
                        $this->classNameToSnakeCase($className) => $className
                    ]);
                }
                return;
            }

            $targetEntity = $classMetadata->getReflectionClass()->getMethod('getImageableEntityClass')->invoke(null);

            $classMetadata->mapManyToMany([
                'fieldName' => 'imageables',
                'mappedBy' => 'images',
                'cascade' => ['persist', 'merge'],
                'fetch' => ClassMetadataInfo::FETCH_LAZY,
                'targetEntity' => $targetEntity,
            ]);
        }
    }

    /**
     * Checks if entity is imageable
     *
     * @param ClassMetadata $classMetadata
     *
     * @return boolean
     */
    private function isImageable(ClassMetadata $classMetadata)
    {
        $classToCheckTrait = $classMetadata->getReflectionClass();
        do {
            if (in_array($this->imageableTrait, $classToCheckTrait->getTraitNames())) {
                return true;
            }
        } while ($classToCheckTrait = $classToCheckTrait->getParentClass());

        return false;
    }

    /**
     * Checks if entity is image
     *
     * @param ClassMetadata $classMetadata
     *
     * @return boolean
     */
    private function isImage(ClassMetadata $classMetadata)
    {
        return in_array(ImageInterface::class, $classMetadata->getReflectionClass()->getInterfaceNames());
    }

    /**
     * @param $className
     *
     * @return string
     */
    private function classNameToSnakeCase($className)
    {
        $reflection = new \ReflectionClass($className);
        return $this->camelToSnakeCaseConverter->normalize(lcfirst($reflection->getShortName()));
    }
}

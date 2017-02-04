<?php

namespace PhpWriterman\ImageBundle\Form\Transformer;


use Doctrine\Common\Persistence\ObjectManager;
use PhpWriterman\ImageBundle\Entity\Image;
use PhpWriterman\ImageBundle\Service\Cropper;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Class ImageToMetadataTransformer
 *
 * @package PhpWriterman\ImageBundle\Form\Transformer
 */
class ImageToMetadataTransformer implements DataTransformerInterface
{
    /**
     * @var ObjectManager
     */
    private $manager;

    /**
     * @var Cropper
     */
    private $cropper;

    /**
     * @var string
     */
    private $webDir;

    /**
     * @var string
     */
    private $imageClass;


    /**
     * ImageToMetadataTransformer constructor.
     *
     * @param ObjectManager $manager
     * @param Cropper $cropper
     * @param $webDir
     * @param $imageClass
     */
    public function __construct(ObjectManager $manager, Cropper $cropper, $webDir, $imageClass)
    {
        $this->manager = $manager;
        $this->cropper = $cropper;
        $this->webDir = $webDir;
        $this->imageClass = $imageClass;
    }

    /**
     * Transforms an object (issue) to a string (number).
     *
     * @param  Image $image
     *
     * @return string
     */
    public function transform($image)
    {
        if (!$image) {
            return null;
        }

        return [
            'id' => $image->getId(),
            'image' => $image->getImage(),
            'original' => $image->getOriginal(),
            'coordinates' => json_encode($image->getCoordinates())
        ];
    }

    /**
     * @param array|null $metadata
     *
     * @return Image
     */
    public function reverseTransform($metadata)
    {
        try {
            /** @var Image $image */
            $image = $this->manager->find($this->imageClass, $metadata['id']);

            $coordinates = json_decode($metadata['coordinates'], true);

            if ($coordinates) {
                foreach (['width', 'height', 'x', 'y'] as $prop) {
                    $coordinates[$prop] = round($coordinates[$prop] ?? 0);
                }

                if ($image->getCoordinates() != $coordinates) {
                    $cropped = $this->generateDestinationPath($image->getOriginal());
                    $this->cropper->crop($this->webDir . $image->getOriginal(), $this->webDir . $cropped, $coordinates);

                    $image->setCropped($cropped);
                    $image->setCoordinates($coordinates);
                }
            }

            return $image;
        } catch (\Exception $e) {
            // causes a validation error
            // this message is not shown to the user
            // see the invalid_message option
            throw new TransformationFailedException($e->getMessage());
        }
    }

    /**
     * @param $original
     *
     * @return string
     */
    protected function generateDestinationPath($original)
    {
        $pathInfo = pathinfo($original);
        $pathInfo['filename'] .= '_cropped';

        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $pathInfo['extension'];
    }

}

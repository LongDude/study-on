<?php

namespace App\Form\DataTransformer;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NumberToCourseTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
    ) {
    }

    public function transform($value): mixed
    {
        if (null === $value) {
            return '';
        }
        return $value->getId();
    }
    public function reverseTransform($value): mixed
    {
        if (!$value) {
            return null;
        }

        $course = $this->manager->getRepository(Course::class)->find($value);
        if (null === $course) {
            throw new TransformationFailedException(sprintf('Course with id "%s" does not exist.', $value));
        }
        return $course;
    }
}

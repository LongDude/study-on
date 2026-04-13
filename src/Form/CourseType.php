<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $course = $builder->getData();

        $builder

            ->add('name', TextType::class, [
                'label' => 'Название курса',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание',
                'required' => true,
            ])
        ;

        if ($course && $course->getSymbolicName() !== null) {
            $builder->add(
                'symbolic_name',
                HiddenType::class,
                [
                    'data' => $course->getSymbolicName(),
                ]
            );
        } else {
            $builder->add('symbolic_name', TextType::class, [
                'label' => 'Символьное имя',
                'required' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}

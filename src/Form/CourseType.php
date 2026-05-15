<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

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

        $builder->add('type', ChoiceType::class, [
            'choices' => [
                'Бесплатный' => 'free',
                'Аренда'  => 'rent',
                'Платный' => 'buy',
            ],
            'data' => $options['course_type'],
            'mapped' => false,
        ]);

        $builder->add('price', NumberType::class, [
            'label' => 'Стоимость',
            'data' => $options['price'],
            'input' => 'number',
            'mapped' => false,
            'required' => false,
            'scale' => 2,
            'attr' => [
                'min' => 0,
                'step' => '0.01',
            ],
            'constraints' => [
                new Assert\PositiveOrZero(),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
            'course_type' => 'free',
            'price' => 0,
        ]);

        $resolver->setAllowedValues('course_type', ['free', 'rent', 'buy']);
        $resolver->setAllowedTypes('price', ['int', 'float']);
    }
}

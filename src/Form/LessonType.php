<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Form\DataTransformer\NumberToCourseTransformer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LessonType extends AbstractType
{
    public function __construct(
        private readonly NumberToCourseTransformer $numberToCourseTransformer
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $lesson = $builder->getData();

        $builder
            ->add('name', TextType::class, ['label' => 'Название'])
            ->add('content', TextareaType::class, ['label' => 'Описание'])
            ->add('index', IntegerType::class, ['label' => "Номер урока"]);

        if ($lesson && $lesson->getCourse() !== null) {
            $builder->add('Course', HiddenType::class, ['data' => $lesson->getCourse(), 'data_class' => null]);
            $builder->get('Course')->addModelTransformer($this->numberToCourseTransformer);
        } else {
            $builder->add('Course', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'name',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
        ]);
    }
}

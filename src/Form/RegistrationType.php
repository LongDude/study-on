<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank(message: "Email не может быть пустым"),
                    new Assert\Email(message:"Введите корректный email адрес")
                ],
                'attr' => ['placeholder' => 'some@email.com']
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Пароли должны совпадать',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options' => [
                    'label' => 'Пароль',
                    'constraints' => [
                        new Assert\NotBlank(message: 'Пароль не может быть пустым'),
                        new Assert\Length(
                            min: 6,
                            minMessage: 'Пароль должен содержать минимум {{ limit }} символов',
                        ),
                    ],
                    'attr' => ['placeholder' => 'Минимум 6 символов']
                ],
                'second_options' => [
                    'label' => 'Повторите пароль',
                    'attr' => ['placeholder' => 'Повторите пароль']
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id' => 'register_form',
        ]);
    }
}

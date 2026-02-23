<?php

namespace App\Form;

use App\Entity\Medicament;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class MedicamentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('medicament', TextType::class, [
                'label' => 'Médicament',
                'required' => true,
                'row_attr' => ['class' => 'form__field'],
                'attr' => [
                    'class' => 'form__input',
                    'placeholder' => 'ex: Amoxicilline',
                ]
            ])
            ->add('dosage', TextType::class, [
                'label' => 'Dosage',
                'required' => false,
                'row_attr' => ['class' => 'form__field'],
                'attr' => [
                    'class' => 'form__input',
                    'placeholder' => 'ex: 500mg',
                ]
            ])
            ->add('duree', TextType::class, [
                'label' => 'Durée',
                'required' => false,
                'row_attr' => ['class' => 'form__field'],
                'attr' => [
                    'class' => 'form__input',
                    'placeholder' => 'ex: 7 jours',
                ]
            ])
            ->add('instructions', TextareaType::class, [
                'label' => 'Instructions',
                'required' => false,
                'row_attr' => ['class' => 'form__field'],
                'attr' => [
                    'class' => 'form__textarea',
                    'rows' => 2,
                    'placeholder' => 'ex: Prendre 3 fois par jour avec nourriture',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Medicament::class,
        ]);
    }
}

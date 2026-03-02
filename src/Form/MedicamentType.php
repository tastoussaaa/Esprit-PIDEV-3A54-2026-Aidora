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
                'row_attr' => ['class' => 'form__field'],
                'attr' => [
                    'class' => 'form__input medicament-input', 
                    'placeholder' => 'Nom du médicament',
                    'autocomplete' => 'off'
                ],
                'required' => true,
            ])
            ->add('dosage', TextType::class, [
                'label' => 'Dosage',
                'row_attr' => ['class' => 'form__field'],
                'attr' => [
                    'class' => 'form__input dosage-input', 
                    'placeholder' => 'Ex: 500mg'
                ],
                'required' => true,
            ])
            ->add('duree', TextType::class, [
                'label' => 'Durée',
                'row_attr' => ['class' => 'form__field'],
                'attr' => [
                    'class' => 'form__input duree-input', 
                    'placeholder' => 'Ex: 7 jours'
                ],
                'required' => true,
            ])
            ->add('instructions', TextareaType::class, [
                'label' => 'Instructions',
                'row_attr' => ['class' => 'form__field'],
                'attr' => [
                    'class' => 'form__textarea instructions-input', 
                    'rows' => 2,
                    'placeholder' => 'Comment prendre le médicament'
                ],
                'required' => false,
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

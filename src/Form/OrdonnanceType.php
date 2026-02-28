<?php

namespace App\Form;

use App\Entity\Consultation;
use App\Entity\Ordonnance;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class OrdonnanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('medicament', TextType::class, [
                'label' => 'Medicament',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'Medicine name']
            ])
            ->add('dosage', TextType::class, [
                'label' => 'Dosage',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'e.g., 2 tablets']
            ])
            ->add('duree', TextType::class, [
                'label' => 'Duration',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'e.g., 7 days']
            ])
            ->add('instructions', TextareaType::class, [
                'label' => 'Instructions',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__textarea', 'rows' => 4, 'placeholder' => 'How to take the medicine']
            ])
            ->add('consultation', EntityType::class, [
                'class' => Consultation::class,
                'choice_label' => function(Consultation $c) {
                    return trim(($c->getName() ?? '') . ' ' . ($c->getFamilyName() ?? '') . ' — ' . ($c->getDateConsultation() ? $c->getDateConsultation()->format('Y-m-d H:i') : ''));
                },
                'label' => 'Consultation (patient)',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__select']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ordonnance::class,
        ]);
    }
}

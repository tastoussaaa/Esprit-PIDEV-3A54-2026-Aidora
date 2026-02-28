<?php

namespace App\Form;

use App\Entity\DemandeAide;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class DemandeAideType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('TitreD', TextType::class, [
                'label' => 'Titre de la demande',
                'attr' => [
                    'minlength' => 10,
                    'maxlength' => 5000,
                    'placeholder' => 'Donnez un titre clair à votre demande...',
                    'required' => true
                ]
            ])

            ->add('typeDemande', ChoiceType::class, [
                'label' => 'Type de demande',
                'choices' => [
                    'Urgent'   => 'URGENT',
                    'Normal'   => 'NORMAL',
                    'Économie' => 'ECONOMIE',
                ],
                'placeholder' => '-- Sélectionner un type --',
                'attr' => ['required' => true]
            ])

            ->add('descriptionBesoin', TextareaType::class, [
                'label' => 'Description du besoin',
                'attr' => [
                    'rows' => 4,
                    'minlength' => 10,
                    'maxlength' => 5000,
                    'placeholder' => 'Décrivez précisément votre besoin...',
                    'required' => true
                ]
            ])

            ->add('typePatient', ChoiceType::class, [
                'label' => 'Type de patient',
                'choices' => [
                    'Personne âgée' => 'PERSONNE_AGEE',
                    'Alzheimer'     => 'ALZHEIMER',
                    'Handicap'      => 'HANDICAP',
                    'Autre'         => 'AUTRE',
                ],
                'placeholder' => '-- Sélectionner un type --',
                'attr' => ['required' => true]
            ])

            ->add('sexe', ChoiceType::class, [
                'label' => 'Sexe',
                'choices' => [
                    'Femme' => 'F',
                    'Homme' => 'M'
                ],
                'placeholder' => '-- Choisir le sexe --',
                'required' => true,
                'attr' => ['required' => true]
            ])

            ->add('dateDebutSouhaitee', DateTimeType::class, [
                'label' => 'Date de début souhaitée',
                'widget' => 'single_text',
                'attr' => ['required' => true]
            ])

            ->add('dateFinSouhaitee', DateTimeType::class, [
                'label' => 'Date de fin souhaitée',
                'widget' => 'single_text',
                'required' => false
            ])

            ->add('budgetMax', IntegerType::class, [
                'label' => 'Budget maximum (DT)',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'max' => 100000,
                    'placeholder' => 'ex: 5000',
                    'type' => 'number'
                ]
            ])

            ->add('besoinCertifie', CheckboxType::class, [
                'label' => 'Besoin d\'un aidant certifié',
                'required' => false
            ])

            ->add('lieu', TextType::class, [
                'label' => 'Nom du lieu (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: Domicile, Hôpital...',
                    'maxlength' => 255
                ]
            ])

            // remplis par la carte
            ->add('latitude', HiddenType::class)
            ->add('longitude', HiddenType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandeAide::class,
        ]);
    }
}
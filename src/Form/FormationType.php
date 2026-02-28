<?php

namespace App\Form;

use App\Entity\Formation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la formation',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le titre est obligatoire.']),
                    new Assert\Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 4, 'class' => 'ckeditor'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La description est obligatoire.']),
                    new Assert\Length([
                        'min' => 20,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('objective', TextareaType::class, [
                'label' => 'Objectif',
                'attr' => ['rows' => 4, 'class' => 'ckeditor'],
                'required' => false
            ])
            ->add('category', TextType::class, [
                'label' => 'Catégorie',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La catégorie est obligatoire.']),
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => 'La catégorie ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La date de début est obligatoire.']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date de début doit être aujourd’hui ou dans le futur.'
                    ])
                ]
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La date de fin est obligatoire.']),
                    new Assert\Callback(function($endDate, $context) {
                        $startDate = $context->getRoot()->get('startDate')->getData();
                        if ($startDate && $endDate <= $startDate) {
                            $context->buildViolation('La date de fin doit être après la date de début.')
                                ->addViolation();
                        }
                    })
                ]
            ]);
        ;

        // Optionally include statut field when controller requests it
        if (!empty($options['include_status'])) {
            $builder->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_combine(Formation::STATUTS, Formation::STATUTS),
                'required' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
        $resolver->setDefined(['include_status']);
        $resolver->setAllowedTypes('include_status', ['bool']);
        $resolver->setDefault('include_status', false);
    }
}

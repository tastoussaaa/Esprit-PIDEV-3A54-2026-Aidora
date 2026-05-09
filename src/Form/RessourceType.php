<?php

namespace App\Form;

use App\Entity\Ressource;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Validator\Constraints\File;

class RessourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre'
            ])

            // 🔥 Description longue
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                      'class' => 'ckeditor'
                ]
            ])

            // ✅ Classe
            ->add('classe', TextType::class, [
                'label' => 'Classe',
                'required' => false
            ])

            // ✅ Matériels (long texte)
            ->add('materiels', TextareaType::class, [
                'label' => 'Matériels nécessaires',
                'required' => false,
                'attr' => [
                    'rows' => 4
                ]
            ])

            // 🔗 URL
            ->add('url', UrlType::class, [
                'label' => 'Lien (URL)',
                'required' => true,
                'default_protocol' => 'https',
            ])

            // 📄 Upload PDF
            ->add('pdfFile', FileType::class, [
                'label' => 'Fichier PDF',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier PDF valide.',
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ressource::class,
        ]);
    }
}

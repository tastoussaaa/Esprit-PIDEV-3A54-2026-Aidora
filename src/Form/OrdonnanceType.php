<?php

namespace App\Form;

use App\Entity\Consultation;
use App\Entity\Ordonnance;
use App\Entity\Medicament;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class OrdonnanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('medicaments', CollectionType::class, [
                'entry_type' => MedicamentType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Médicaments',
                'attr' => [
                    'class' => 'medicaments-collection',
                ],
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

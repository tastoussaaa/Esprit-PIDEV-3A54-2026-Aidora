<?php 

namespace App\Form;

use App\Entity\Consultation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class ConsultationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateConsultation', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Consultation date',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input']
            ])
            ->add('timeSlot', ChoiceType::class, [
                'label' => 'Time slot',
                'choices' => [
                    // Morning shift
                    '9:00' => '09:00',
                    '9:30' => '09:30',
                    '10:00' => '10:00',
                    '10:30' => '10:30',
                    '11:00' => '11:00',
                    '11:30' => '11:30',
                    // Evening shift
                    '14:00' => '14:00',
                    '14:30' => '14:30',
                    '15:00' => '15:00',
                    '15:30' => '15:30',
                    '16:00' => '16:00',
                    '16:30' => '16:30',
                ],
                'placeholder' => 'Choose a time slot',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__select']
            ])
            ->add('motif', TextType::class, [
                'label' => 'Motif',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'Reason for consultation']
            ])
            ->add('name', TextType::class, [
                'label' => 'First name',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'Given name']
            ])
            ->add('familyName', TextType::class, [
                'label' => 'Family name',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'Surname']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'patient@example.com']
            ])
            ->add('sex', ChoiceType::class, [
                'label' => 'Sex',
                'choices' => [
                    'Male' => 'male',
                    'Female' => 'female',
                ],
                'placeholder' => 'Choose',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__select']
            ])
            ->add('age', IntegerType::class, [
                'label' => 'Age',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'min' => 0]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Consultation::class,
        ]);
    }
}
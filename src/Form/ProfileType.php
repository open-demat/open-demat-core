<?php

/**
 * Open Demat Core – Profile Form Type
 *
 * Ce formulaire Symfony permet de gérer l’édition du profil utilisateur
 * dans la plateforme Core Open Demat. Il expose les champs personnels et
 * professionnels de l’utilisateur ainsi que certains champs métier
 * utilisés dans les applications de la plateforme.
 *
 * Le formulaire est utilisé dans l’espace profil afin de permettre à
 * l’utilisateur de consulter et modifier certaines informations le
 * concernant. Certains champs, comme l’identifiant et l’email, sont
 * affichés mais non modifiables.
 *
 * Des champs supplémentaires non mappés permettent également de saisir
 * certaines informations métier qui sont ensuite transformées avant
 * d’être enregistrées dans l’entité User.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Form;

use OpenDemat\Core\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // username readonly (non mappé)
            ->add('username', TextType::class, [
                'label' => 'Identifiant',
                'mapped' => false,
                'disabled' => true,
                'data' => $options['username_value'],
                'required' => false,
            ])

            // === Infos personnelles ===
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => false,
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('fonction', TextType::class, [
                'label' => 'Fonction',
                'required' => false,
            ])
            ->add('service', TextType::class, [
                'label' => 'Service',
                'required' => false,
            ])
            ->add('site', TextType::class, [
                'label' => 'Site',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'disabled' => true,
                'required' => false,
            ])

            // === Champs métier (texte simple, non mappés) ===
            ->add('composante_text', TextType::class, [
                'label' => 'Composante',
                'mapped' => false,
                'required' => false,
                'data' => $options['composante_text_value'],
                'attr' => [
                    'placeholder' => 'Ex : UFR Sciences',
                ],
            ])
            ->add('dep_composante_text', TextType::class, [
                'label' => 'Département',
                'mapped' => false,
                'required' => false,
                'data' => $options['dep_text_value'],
                'attr' => [
                    'placeholder' => 'Ex : Informatique',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'username_value' => '',
            'composante_text_value' => '',
            'dep_text_value' => '',
        ]);
    }
}

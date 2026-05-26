<?php

/**
 * Open Demat Core – User Document Upload Form
 *
 * Ce formulaire Symfony permet de téléverser un document dans
 * l’espace documentaire personnel d’un utilisateur. Il est utilisé
 * dans l’interface de profil pour ajouter un fichier au "vault"
 * personnel de l’utilisateur.
 *
 * Le formulaire gère la sélection du fichier, la validation du
 * format et de la taille, ainsi que certaines métadonnées
 * optionnelles comme un libellé, une catégorie ou un marquage
 * d’épinglage pour l’affichage.
 *
 * Les données du formulaire ne sont pas directement mappées à une
 * entité Doctrine : elles sont traitées manuellement dans le
 * contrôleur afin de créer les entités Document et UserDocument.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserDocumentUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Fichier',
                'mapped' => false,
                'constraints' => [
                    new Assert\NotNull(message: 'Veuillez sélectionner un fichier.'),
                    // adapte si tu veux restreindre
                    new Assert\File(
                        maxSize: '15M',
                        mimeTypes: [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Formats autorisés : PDF, JPG, PNG, WEBP.',
                    ),
                ],
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'required' => false,
                'mapped' => false,
                'attr' => ['placeholder' => 'Ex: RIB, CNI recto...'],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'mapped' => false,
                'choices' => [
                    '—' => null,
                    'Identité' => 'identite',
                    'Banque' => 'banque',
                    'Domicile' => 'domicile',
                    'Autre' => 'autre',
                ],
            ])
            ->add('isPinned', CheckboxType::class, [
                'label' => 'Épingler',
                'required' => false,
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

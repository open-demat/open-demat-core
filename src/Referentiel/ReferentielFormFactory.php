<?php

/**
 * Open Demat Core – Référentiel Form Factory
 *
 * Ce service permet de générer dynamiquement des formulaires Symfony
 * pour les entités de référentiel de la plateforme Core Open Demat.
 *
 * À partir d’une définition de référentiel (`ReferentielDefinition`)
 * et des métadonnées Doctrine de l’entité concernée, il construit
 * automatiquement un formulaire permettant de créer ou modifier
 * les entrées du référentiel.
 *
 * Les champs du formulaire sont déterminés à partir de la liste
 * des champs éditables définis dans la configuration du référentiel.
 * Le type de champ Symfony est déduit automatiquement du type
 * Doctrine correspondant (string, boolean, date, etc.).
 *
 * Ce mécanisme permet de générer des interfaces d’administration
 * génériques pour les référentiels sans avoir à écrire de FormType
 * spécifique pour chaque entité.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Referentiel;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type as F;
use Symfony\Component\Validator\Constraints as Assert;

final class ReferentielFormFactory
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly EntityManagerInterface $em,
    ) {}

    public function create(ReferentielDefinition $def, object $entity, array $options = []): FormInterface
    {
        $meta = $this->em->getClassMetadata($def->entityClass);

        $builder = $this->forms->createBuilder(F\FormType::class, $entity, $options);

        foreach ($def->editableFields as $field) {
            // Type Doctrine (string/bool/datetime/etc.)
            $mapping = $meta->getFieldMapping($field);
            $type = $mapping['type'] ?? 'string';

            // Types simples par défaut
            $formType = match ($type) {
                'boolean' => F\CheckboxType::class,
                'integer', 'smallint', 'bigint' => F\IntegerType::class,
                'datetime_immutable', 'datetime' => F\DateTimeType::class,
                'date_immutable', 'date' => F\DateType::class,
                default => F\TextType::class,
            };

            // Petites règles par défaut (optionnelles)
            $constraints = [];
            if (($mapping['nullable'] ?? false) === false && $field !== 'actif') {
                $constraints[] = new Assert\NotBlank();
            }

            $builder->add($field, $formType, [
                'required' => (($mapping['nullable'] ?? false) === false) && $field !== 'actif',
                'constraints' => $constraints,
                'label' => $this->prettyLabel($field),
            ]);
        }

        return $builder->getForm();
    }

    private function prettyLabel(string $field): string
    {
        // Exemple simple: "createdAt" -> "Created at"
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $field) ?? $field;
        return ucfirst($label);
    }
}

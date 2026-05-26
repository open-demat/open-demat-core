<?php

/**
 * Open Demat Core – Process Form Factory
 *
 * Ce service permet de générer dynamiquement des formulaires Symfony
 * pour les entités de processus métier du Core Open Demat. Il construit un
 * formulaire à partir de la définition d’un processus (`ProcessEntityDefinition`)
 * et des métadonnées Doctrine associées à l’entité concernée.
 *
 * Les champs du formulaire sont déterminés automatiquement à partir
 * de la configuration du processus et du type des propriétés Doctrine,
 * ce qui permet de créer des formulaires génériques sans écrire de
 * FormType spécifique pour chaque entité métier.
 *
 * Ce mécanisme facilite la création rapide de nouveaux processus
 * applicatifs en s’appuyant sur une configuration déclarative
 * plutôt que sur du code spécifique.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Process;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class ProcessFormFactory
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly ManagerRegistry $doctrine,
    ) {}

    public function create(ProcessEntityDefinition $def, object $entity): FormInterface
    {
        $em = $this->doctrine->getManagerForClass($def->entityClass);
        $meta = $em->getClassMetadata($def->entityClass);

        $builder = $this->forms->createBuilder(
            type: FormType::class,
            data: $entity,
            options: [
                'data_class' => $def->entityClass,
            ]
        );

        foreach ($def->formFields as $field) {
            if (!$this->isProbablyReadableWritable($entity, $field)) {
                continue;
            }

            [$type, $options] = $this->guessTypeAndOptions($meta, $field);

            $builder->add($field, $type, $options + [
                'required' => false,
                'label' => $this->humanize($field),
            ]);
        }

        return $builder->getForm();
    }

    private function guessTypeAndOptions(\Doctrine\ORM\Mapping\ClassMetadata $meta, string $field): array
    {
        // 1) Associations Doctrine -> EntityType
        if ($meta->hasAssociation($field)) {
            $map = $meta->getAssociationMapping($field);

            // ManyToOne / OneToOne: simple select
            if (in_array($map['type'], [\Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_ONE, \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_ONE], true)) {
                return [EntityType::class, [
                    'class' => $map['targetEntity'],
                    'choice_label' => 'id', // à améliorer (ex: __toString)
                    'placeholder' => '—',
                ]];
            }

            // ToMany: multi-select
            if (in_array($map['type'], [\Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_MANY, \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_MANY], true)) {
                return [EntityType::class, [
                    'class' => $map['targetEntity'],
                    'choice_label' => 'id',
                    'multiple' => true,
                    'expanded' => false,
                ]];
            }
        }

        // 2) Champs scalaires Doctrine
        if ($meta->hasField($field)) {
            $doctrineType = $meta->getTypeOfField($field);

            return match ($doctrineType) {
                'text' => [TextareaType::class, []],
                'boolean' => [CheckboxType::class, []],
                'integer', 'smallint', 'bigint' => [IntegerType::class, []],
                'float', 'decimal' => [NumberType::class, []],
                'date_immutable', 'date' => [DateType::class, ['widget' => 'single_text']],
                'datetime_immutable', 'datetime' => [DateTimeType::class, ['widget' => 'single_text']],
                'json' => [TextareaType::class, ['help' => 'JSON']],
                default => [TextType::class, []],
            };
        }

        // fallback
        return [TextType::class, []];
    }

    private function isProbablyReadableWritable(object $entity, string $field): bool
    {
        $uc = ucfirst($field);
        $hasGetter = method_exists($entity, 'get' . $uc) || method_exists($entity, 'is' . $uc) || property_exists($entity, $field);
        $hasSetter = method_exists($entity, 'set' . $uc) || property_exists($entity, $field);
        return $hasGetter && $hasSetter;
    }

    private function humanize(string $name): string
    {
        $s = preg_replace('/(?<!^)[A-Z]/', ' $0', $name) ?? $name;
        $s = str_replace(['_', '-'], ' ', $s);
        return ucfirst(trim($s));
    }
}

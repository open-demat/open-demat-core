<?php

/**
 * Open Demat Core – Application Definition Interface
 *
 * Cette interface définit le contrat que doivent respecter les
 * définitions d’applications enregistrées dans le Hub du Core Open Demat.
 *
 * Elle permet de décrire les informations nécessaires pour référencer
 * une application dans le portail, notamment son identifiant,
 * son titre, sa route d’accès, son icône, les rôles requis et
 * sa description.
 *
 * Les implémentations de cette interface sont utilisées par le
 * système de registre d’applications afin de synchroniser et
 * d’afficher dynamiquement les modules disponibles dans la
 * plateforme.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Hub;

interface AppDefinitionInterface
{
    public function getKey(): string;
    public function getTitle(): string;
    public function getRoute(): string;
    public function getIcon(): string;
    public function getRoles(): array;
    public function getDescription(): string;
}

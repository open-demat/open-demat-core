<?php

/**
 * Open Demat Core – Task Controller
 *
 * Ce contrôleur gère l’accès aux tâches assignées à l’utilisateur connecté.
 * Il permet d’afficher les tâches en attente dans la boîte de réception
 * (inbox) ainsi que de récupérer le nombre de tâches ouvertes via une
 * API JSON utilisée notamment pour les interfaces dynamiques.
 *
 * Les tâches sont filtrées en fonction de l’utilisateur et de ses rôles
 * explicites stockés en base de données afin de déterminer les actions
 * qu’il peut traiter dans les différents processus applicatifs.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenDemat\Core\Repository\TaskRepository;

class TaskController extends AbstractController
{
    #[Route('/tasks/inbox', name: 'core_tasks_inbox', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function inbox(TaskRepository $taskRepository): Response
    {
        /** @var \OpenDemat\Core\Entity\User $user */
        $user = $this->getUser();

        // IMPORTANT: rôles explicites uniquement (stockés en BDD)
        $explicitRoles = array_values(array_unique($user->getRoles()));

        $tasks = $taskRepository->findOpenForUserAndRoleNames($user, $explicitRoles);

        return $this->render('task/_inbox_list.html.twig', [
            'tasks' => $tasks,
        ]);
    }

    #[Route('/tasks/inbox/count', name: 'core_tasks_inbox_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function inboxCount(TaskRepository $repo): JsonResponse
    {
        /** @var \OpenDemat\Core\Entity\User $user */
        $user = $this->getUser();

        // IMPORTANT: rôles explicites uniquement (stockés en BDD)
        $explicitRoles = array_values(array_unique($user->getRoles()));

        $count = $repo->countOpenForUserAndRoleNames($user, $explicitRoles);

        return $this->json(['count' => $count]);
    }
}

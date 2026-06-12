<?php

namespace OpenDemat\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenDemat\Core\Directory\LdapPersonnelDirectory;

final class LdapPersonnelController extends AbstractController
{
    #[Route('/api/ldap/personnel', name: 'open_demat_ldap_personnel_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function search(Request $request, LdapPersonnelDirectory $directory): JsonResponse
    {
        $query = trim((string) $request->query->get('q'));
        $limit = (int) $request->query->get('limit', 12);

        if (mb_strlen($query) < 2) {
            return $this->json(['items' => []]);
        }

        try {
            $items = array_map(
                static fn($entry): array => $entry->toArray(),
                $directory->search($query, $limit)
            );
        } catch (\Throwable $exception) {
            return $this->json([
                'items' => [],
                'error' => 'ldap_unavailable',
            ], 503);
        }

        return $this->json(['items' => $items]);
    }
}

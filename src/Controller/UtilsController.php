<?php

namespace OpenDemat\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/utils')]
class UtilsController extends AbstractController
{
    #[Route('/commune', name: 'open_demat_core_utils_commune', methods: ['GET'])]
    public function commune(
        Request $request,
        HttpClientInterface $httpClient
    ): JsonResponse {
        $codePostal = trim((string) $request->query->get('codePostal'));

        if (!preg_match('/^\d{5}$/', $codePostal)) {
            return $this->json([
                'ok' => false,
                'message' => 'Code postal invalide.',
            ], 400);
        }

        try {
            $response = $httpClient->request('GET', 'https://geo.api.gouv.fr/communes', [
                'query' => [
                    'codePostal' => $codePostal,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return $this->json([
                    'ok' => false,
                    'message' => 'Service indisponible.',
                ], 502);
            }

            $data = $response->toArray(false);

            if (empty($data)) {
                return $this->json([
                    'ok' => false,
                    'message' => 'Aucune commune trouvée.',
                ], 404);
            }

            $villes = [];
            foreach ($data as $row) {
                if (!empty($row['nom'])) {
                    $villes[] = [
                        'nom' => (string) $row['nom'],
                        'code' => isset($row['code']) ? (string) $row['code'] : null,
                    ];
                }
            }

            $villes = array_values(array_unique($villes, SORT_REGULAR));

            if (count($villes) === 1) {
                return $this->json([
                    'ok' => true,
                    'mode' => 'single',
                    'ville' => $villes[0]['nom'],
                    'codePostal' => $codePostal,
                ]);
            }

            return $this->json([
                'ok' => true,
                'mode' => 'multiple',
                'codePostal' => $codePostal,
                'villes' => $villes,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'message' => 'Service indisponible.',
            ], 502);
        }
    }
}
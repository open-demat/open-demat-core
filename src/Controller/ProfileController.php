<?php

/**
 * Open Demat Core – Profile Controller
 *
 * Ce contrôleur gère l’espace profil de l’utilisateur connecté.
 * Il permet de consulter et modifier les informations du profil,
 * d’ajouter, télécharger et supprimer des documents personnels,
 * ainsi que de récupérer ces données au format JSON pour des usages AJAX.
 *
 * Il centralise donc la gestion du compte utilisateur côté Core,
 * en s’appuyant sur les formulaires Symfony, Doctrine et le stockage
 * des documents via Flysystem.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Controller;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenDemat\Core\Entity\Document;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Entity\UserDocument;
use OpenDemat\Core\Form\ProfileType;
use OpenDemat\Core\Form\UserDocumentUploadType;
use OpenDemat\Core\Repository\UserDocumentRepository;

#[Route('/profile', name: 'open_demat_core_profile_')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    private const USER_VAULT_BUCKET = 'documents';

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserDocumentRepository $userDocumentRepo,
        FilesystemOperator $documentsStorage,
    ): Response {
        // User sécurité -> User doctrine managé
        $securityUser = $this->getUser();
        if (!$securityUser instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur invalide.');
        }

        $user = $em->getRepository(User::class)->find($securityUser->getId());
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur introuvable.');
        }

        // 1) Form profil (inclut maintenant nom/prenom/tel/fonction/service/site via ProfileType)
        $profileForm = $this->createForm(ProfileType::class, $user, [
            'username_value' => $user->getUsername(),
            'composante_text_value' => implode("\n", $user->getComposante()),
            'dep_text_value' => implode("\n", $user->getDepComposante()),
        ]);
        $profileForm->handleRequest($request);

        // 2) Form upload doc perso
        $uploadForm = $this->createForm(UserDocumentUploadType::class);
        $uploadForm->handleRequest($request);

        // Submit profil
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            /** @var User $data */
            $data = $profileForm->getData();

            // Arrays JSON (1 par ligne)
            $compoText = (string) $profileForm->get('composante_text')->getData();
            $depText   = (string) $profileForm->get('dep_composante_text')->getData();

            $toArray = static function (string $txt): array {
                $lines = preg_split("/\R/u", $txt) ?: [];
                $lines = array_map('trim', $lines);
                $lines = array_filter($lines, static fn($v) => $v !== '');
                return array_values(array_unique($lines));
            };

            $data->setComposante($toArray($compoText));
            $data->setDepComposante($toArray($depText));

            // ⚠️ Les champs nom/prenom/tel/fonction/service/site sont mappés dans le form
            // => Doctrine les persiste via flush()
            $em->flush();

            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('open_demat_core_profile_index');
        }

        // Submit upload doc perso
        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $uploadForm->get('file')->getData();
            if (!$file instanceof UploadedFile) {
                $this->addFlash('danger', 'Fichier invalide.');
                return $this->redirectToRoute('open_demat_core_profile_index');
            }

            $label    = $uploadForm->get('label')->getData();
            $category = $uploadForm->get('category')->getData();
            $isPinned = (bool) $uploadForm->get('isPinned')->getData();

            $originalName = $file->getClientOriginalName() ?: 'document';
            $mimeType = $file->getClientMimeType() ?: 'application/octet-stream';
            $sizeBytes = (int) $file->getSize();
            $checksum = @hash_file('sha256', $file->getPathname()) ?: null;

            $document = new Document(
                originalName: $originalName,
                mimeType: $mimeType,
                sizeBytes: $sizeBytes,
                bucket: self::USER_VAULT_BUCKET,
                checksumSha256: $checksum,
                uploadedBy: $user,
            );

            $path = self::USER_VAULT_BUCKET . '/' . $document->getId()->toRfc4122();

            $stream = fopen($file->getPathname(), 'rb');
            if ($stream === false) {
                $this->addFlash('danger', 'Impossible de lire le fichier uploadé.');
                return $this->redirectToRoute('open_demat_core_profile_index');
            }

            try {
                $documentsStorage->writeStream($path, $stream, [
                    'visibility' => 'private',
                    'mimetype' => $mimeType,
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $userDoc = new UserDocument(
                user: $user,
                document: $document,
                label: is_string($label) && trim($label) !== '' ? trim($label) : null,
                category: is_string($category) && trim($category) !== '' ? trim($category) : null,
                isPinned: $isPinned,
                createdBy: $user,
            );

            $em->persist($document);
            $em->persist($userDoc);
            $em->flush();

            $this->addFlash('success', 'Document ajouté à vos fichiers personnels.');
            return $this->redirectToRoute('open_demat_core_profile_index');
        }

        $userDocs = $userDocumentRepo->findForUser($user);

        return $this->render('profile/index.html.twig', [
            'form' => $profileForm->createView(),
            'uploadForm' => $uploadForm->createView(),
            'userDocs' => $userDocs,
            'user' => $user,
        ]);
    }

    #[Route('/documents/{id}/download', name: 'download', methods: ['GET'])]
    public function download(
        int $id,
        EntityManagerInterface $em,
        UserDocumentRepository $repo,
        FilesystemOperator $documentsStorage,
    ): Response {
        $securityUser = $this->getUser();
        if (!$securityUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $user = $em->getRepository(User::class)->find($securityUser->getId());
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $userDoc = $repo->find($id);
        if (!$userDoc) {
            throw $this->createNotFoundException();
        }

        if ($userDoc->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        $doc = $userDoc->getDocument();
        $path = $doc->getBucket() . '/' . $doc->getId()->toRfc4122();

        $response = new StreamedResponse(function () use ($documentsStorage, $path) {
            $stream = $documentsStorage->readStream($path);
            if ($stream === false) {
                return;
            }
            $out = fopen('php://output', 'wb');
            if ($out !== false) {
                stream_copy_to_stream($stream, $out);
                fclose($out);
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $doc->getMimeType());
        $safeName = str_replace(['"', "\r", "\n"], '', $doc->getOriginalName());
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $safeName . '"');

        return $response;
    }

    #[Route('/documents/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserDocumentRepository $repo,
    ): Response {
        $securityUser = $this->getUser();
        if (!$securityUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $user = $em->getRepository(User::class)->find($securityUser->getId());
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $userDoc = $repo->find($id);
        if (!$userDoc) {
            throw $this->createNotFoundException();
        }

        if ($userDoc->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        if (!$this->isCsrfTokenValid('ud_delete_' . $userDoc->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $em->remove($userDoc);
        $em->flush();

        $this->addFlash('success', 'Document supprimé de vos fichiers personnels.');
        return $this->redirectToRoute('open_demat_core_profile_index');
    }

    #[Route('/me/ajax', name: 'ajax_me', methods: ['GET'])]
    public function ajaxMe(
        EntityManagerInterface $em,
        UserDocumentRepository $userDocumentRepo,
    ): JsonResponse {
        $securityUser = $this->getUser();
        if (!$securityUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $id = (int) $securityUser->getId();

        $user = $em->getRepository(User::class)->find($id);
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $userDocs = $userDocumentRepo->findForUser($user);

        $docs = array_map(static function (UserDocument $ud): array {
            $doc = $ud->getDocument();

            return [
                'id' => $ud->getId(),
                'label' => $ud->getLabel(),
                'category' => $ud->getCategory(),
                'isPinned' => $ud->isPinned(),
                'createdAt' => $ud->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'document' => [
                    'id' => $doc->getId()->toRfc4122(),
                    'originalName' => $doc->getOriginalName(),
                    'mimeType' => $doc->getMimeType(),
                    'sizeBytes' => $doc->getSizeBytes(),
                    'checksumSha256' => $doc->getChecksumSha256(),
                ],
            ];
        }, $userDocs);

        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'tel' => $user->getTelephone(),
                'fonction' => $user->getFonction(),
                'service' => $user->getService(),
                'site' => $user->getSite(),
                'composante' => $user->getComposante(),
                'depComposante' => $user->getDepComposante(),
            ],
            'documents' => $docs,
        ]);
    }

    #[Route('/{id}/ajax', name: 'ajax_get', methods: ['GET'])]
    public function ajaxGetProfile(
        int $id,
        EntityManagerInterface $em,
        UserDocumentRepository $userDocumentRepo,
    ): JsonResponse {
        $securityUser = $this->getUser();
        if (!$securityUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // règle demandée : le demandeur doit être égal à l'id demandé
        if ($securityUser->getId() !== $id) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $user = $em->getRepository(User::class)->find($id);
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $userDocs = $userDocumentRepo->findForUser($user);

        $docs = array_map(static function (UserDocument $ud): array {
            $doc = $ud->getDocument();

            return [
                'id' => $ud->getId(), // id du UserDocument (celui utilisé par download/delete)
                'label' => $ud->getLabel(),
                'category' => $ud->getCategory(),
                'isPinned' => $ud->isPinned(),
                'createdAt' => $ud->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'document' => [
                    'id' => $doc->getId()->toRfc4122(),
                    'originalName' => $doc->getOriginalName(),
                    'mimeType' => $doc->getMimeType(),
                    'sizeBytes' => $doc->getSizeBytes(),
                    'checksumSha256' => $doc->getChecksumSha256(),
                ],
            ];
        }, $userDocs);

        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),

                // champs profil (à adapter à tes getters exacts)
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'tel' => $user->getTel(),
                'fonction' => $user->getFonction(),
                'service' => $user->getService(),
                'site' => $user->getSite(),

                'composante' => $user->getComposante(),
                'depComposante' => $user->getDepComposante(),
            ],
            'documents' => $docs,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\Map;
use App\Entity\MapShare;
use App\Entity\User;
use App\Repository\MapRepository;
use App\Repository\MapShareRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/maps')]
class MapController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MapRepository $mapRepository,
        private MapShareRepository $mapShareRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        $ownMaps = $this->mapRepository->findByOwner($user);
        $sharedMaps = $this->mapRepository->findSharedWithUser($user);

        return $this->json([
            'own' => array_map(fn(Map $m) => $this->serializeMap($m), $ownMaps),
            'shared' => array_map(fn(Map $m) => $this->serializeMap($m, false), $sharedMaps),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $map = new Map();
        $map->setOwner($this->getUser());
        $map->setName($data['name'] ?? 'Sans titre');
        $map->setDescription($data['description'] ?? null);
        $map->setStatus($data['status'] ?? 'draft');
        $map->setCenterLng($data['centerLng'] ?? -0.5670392);
        $map->setCenterLat($data['centerLat'] ?? 44.82459);
        $map->setZoom($data['zoom'] ?? 12);
        $map->setChanges($data['changes'] ?? []);
        $map->setSplits($data['splits'] ?? []);

        $this->em->persist($map);
        $this->em->flush();

        return $this->json($this->serializeMap($map), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $map = $this->mapRepository->find($id);
        if (!$map) {
            return $this->json(['error' => 'Carte non trouvée'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccess($map)) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $isOwner = $map->getOwner() === $this->getUser();
        return $this->json($this->serializeMap($map, $isOwner));
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $map = $this->mapRepository->find($id);
        if (!$map) {
            return $this->json(['error' => 'Carte non trouvée'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canEdit($map)) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $map->setName($data['name']);
        if (isset($data['description'])) $map->setDescription($data['description']);
        if (isset($data['status'])) $map->setStatus($data['status']);
        if (isset($data['centerLng'])) $map->setCenterLng($data['centerLng']);
        if (isset($data['centerLat'])) $map->setCenterLat($data['centerLat']);
        if (isset($data['zoom'])) $map->setZoom($data['zoom']);
        if (array_key_exists('changes', $data)) $map->setChanges($data['changes']);
        if (array_key_exists('splits', $data)) $map->setSplits($data['splits']);

        $map->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json($this->serializeMap($map));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $map = $this->mapRepository->find($id);
        if (!$map) {
            return $this->json(['error' => 'Carte non trouvée'], Response::HTTP_NOT_FOUND);
        }

        if ($map->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($map);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(int $id): JsonResponse
    {
        $original = $this->mapRepository->find($id);
        if (!$original) {
            return $this->json(['error' => 'Carte non trouvée'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canAccess($original)) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $copy = new Map();
        $copy->setOwner($this->getUser());
        $copy->setName($original->getName() . ' (copie)');
        $copy->setDescription($original->getDescription());
        $copy->setStatus('draft');
        $copy->setCenterLng($original->getCenterLng());
        $copy->setCenterLat($original->getCenterLat());
        $copy->setZoom($original->getZoom());
        $copy->setChanges($original->getChanges());
        $copy->setSplits($original->getSplits());
        $copy->setDuplicatedFrom($original);

        $this->em->persist($copy);
        $this->em->flush();

        return $this->json($this->serializeMap($copy), Response::HTTP_CREATED);
    }

    #[Route('/{id}/share', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function share(int $id, Request $request): JsonResponse
    {
        $map = $this->mapRepository->find($id);
        if (!$map) {
            return $this->json(['error' => 'Carte non trouvée'], Response::HTTP_NOT_FOUND);
        }

        if ($map->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $share = new MapShare();
        $share->setMap($map);
        $share->setCanEdit($data['canEdit'] ?? false);

        if (!empty($data['email'])) {
            $targetUser = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if (!$targetUser) {
                return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
            }
            $share->setSharedWith($targetUser);
        } else {
            $share->setToken(bin2hex(random_bytes(32)));
        }

        $this->em->persist($share);
        $this->em->flush();

        return $this->json([
            'id' => $share->getId(),
            'email' => $share->getSharedWith()?->getEmail(),
            'token' => $share->getToken(),
            'canEdit' => $share->isCanEdit(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/share/{shareId}', methods: ['DELETE'], requirements: ['id' => '\d+', 'shareId' => '\d+'])]
    public function revokeShare(int $id, int $shareId): JsonResponse
    {
        $map = $this->mapRepository->find($id);
        if (!$map || $map->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $share = $this->mapShareRepository->find($shareId);
        if (!$share || $share->getMap()->getId() !== $map->getId()) {
            return $this->json(['error' => 'Partage non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($share);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/public', methods: ['GET'])]
    public function publicList(): JsonResponse
    {
        $maps = $this->mapRepository->findPublicMaps();
        return $this->json(array_map(fn(Map $m) => $this->serializeMap($m, false), $maps));
    }

    #[Route('/public/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function publicShow(int $id): JsonResponse
    {
        $map = $this->mapRepository->find($id);
        if (!$map || $map->getStatus() !== 'public') {
            return $this->json(['error' => 'Carte non trouvée'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeMap($map, false));
    }

    #[Route('/shared/{token}', methods: ['GET'])]
    public function sharedByToken(string $token): JsonResponse
    {
        $share = $this->mapShareRepository->findByToken($token);
        if (!$share) {
            return $this->json(['error' => 'Lien de partage invalide'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(array_merge(
            $this->serializeMap($share->getMap(), false),
            ['canEdit' => $share->isCanEdit()]
        ));
    }

    private function canAccess(Map $map): bool
    {
        $user = $this->getUser();
        if ($map->getOwner() === $user) return true;
        if ($map->getStatus() === 'public') return true;

        foreach ($map->getShares() as $share) {
            if ($share->getSharedWith() === $user) return true;
        }

        return false;
    }

    private function canEdit(Map $map): bool
    {
        $user = $this->getUser();
        if ($map->getOwner() === $user) return true;

        foreach ($map->getShares() as $share) {
            if ($share->getSharedWith() === $user && $share->isCanEdit()) return true;
        }

        return false;
    }

    private function serializeMap(Map $map, bool $includeShares = true): array
    {
        $result = [
            'id' => $map->getId(),
            'name' => $map->getName(),
            'description' => $map->getDescription(),
            'status' => $map->getStatus(),
            'centerLng' => $map->getCenterLng(),
            'centerLat' => $map->getCenterLat(),
            'zoom' => $map->getZoom(),
            'changes' => $map->getChanges(),
            'splits' => $map->getSplits(),
            'ownerEmail' => $map->getOwner()->getEmail(),
            'createdAt' => $map->getCreatedAt()->format('c'),
            'updatedAt' => $map->getUpdatedAt()->format('c'),
            'duplicatedFromId' => $map->getDuplicatedFrom()?->getId(),
        ];

        if ($includeShares) {
            $result['shares'] = array_map(fn(MapShare $s) => [
                'id' => $s->getId(),
                'email' => $s->getSharedWith()?->getEmail(),
                'token' => $s->getToken(),
                'canEdit' => $s->isCanEdit(),
            ], $map->getShares()->toArray());
        }

        return $result;
    }
}

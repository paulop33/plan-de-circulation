<?php

namespace App\Controller;

use App\Entity\Map;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/users', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        $users = $this->em->getRepository(User::class)->findAll();
        return $this->json(array_map(fn(User $u) => [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'roles' => $u->getRoles(),
            'createdAt' => $u->getCreatedAt()->format('c'),
        ], $users));
    }

    #[Route('/users/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteUser(int $id): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Prevent self-deletion
        if ($user === $this->getUser()) {
            return $this->json(['error' => 'Impossible de supprimer votre propre compte'], Response::HTTP_BAD_REQUEST);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/maps', methods: ['GET'])]
    public function listMaps(): JsonResponse
    {
        $maps = $this->em->getRepository(Map::class)->findBy([], ['updatedAt' => 'DESC']);
        return $this->json(array_map(fn(Map $m) => [
            'id' => $m->getId(),
            'name' => $m->getName(),
            'status' => $m->getStatus(),
            'ownerEmail' => $m->getOwner()->getEmail(),
            'createdAt' => $m->getCreatedAt()->format('c'),
            'updatedAt' => $m->getUpdatedAt()->format('c'),
        ], $maps));
    }

    #[Route('/maps/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteMap(int $id): JsonResponse
    {
        $map = $this->em->getRepository(Map::class)->find($id);
        if (!$map) {
            return $this->json(['error' => 'Carte non trouvée'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($map);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

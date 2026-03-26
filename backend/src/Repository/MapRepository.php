<?php

namespace App\Repository;

use App\Entity\Map;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MapRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Map::class);
    }

    public function findByOwner(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('m.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublicMaps(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.status = :status')
            ->setParameter('status', 'public')
            ->orderBy('m.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findSharedWithUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.shares', 's')
            ->where('s.sharedWith = :user')
            ->setParameter('user', $user)
            ->orderBy('m.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

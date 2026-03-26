<?php

namespace App\Repository;

use App\Entity\MapShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MapShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MapShare::class);
    }

    public function findByToken(string $token): ?MapShare
    {
        return $this->findOneBy(['token' => $token]);
    }
}

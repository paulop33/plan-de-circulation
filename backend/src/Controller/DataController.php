<?php

namespace App\Controller;

use App\Dto\BboxQuery;
use App\Service\OsmDataService;
use App\Service\ParlonsVeloService;
use App\Service\PunctualTrafficService;
use App\Service\TrafficService;
use App\Service\TransitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class DataController extends AbstractController
{
    public function __construct(
        private OsmDataService $osmDataService,
        private TrafficService $trafficService,
        private PunctualTrafficService $punctualTrafficService,
        private ParlonsVeloService $parlonsVeloService,
        private TransitService $transitService,
    ) {}

    #[Route('/api/data', methods: ['GET'])]
    public function getData(#[MapQueryString] BboxQuery $bbox): JsonResponse
    {
        return $this->json($this->osmDataService->getFeatureCollection($bbox));
    }

    #[Route('/api/traffic', methods: ['GET'])]
    public function getTraffic(): JsonResponse
    {
        return $this->json($this->trafficService->getFeatureCollection());
    }

    #[Route('/api/punctual-traffic', methods: ['GET'])]
    public function getPunctualTraffic(): JsonResponse
    {
        return $this->json($this->punctualTrafficService->getFeatureCollection());
    }

    #[Route('/api/parlons-velo', methods: ['GET'])]
    public function getParlonsVelo(#[MapQueryString] BboxQuery $bbox): JsonResponse
    {
        return $this->json($this->parlonsVeloService->getFeatureCollection($bbox));
    }

    #[Route('/api/transit', methods: ['GET'])]
    public function getTransit(): JsonResponse
    {
        return $this->json($this->transitService->getFeatureCollection());
    }
}

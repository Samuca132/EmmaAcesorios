<?php

namespace AppBundle\Controller;

use AppBundle\Repository\DashboardRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DashboardController extends ApiController
{
    private $dashboard;

    public function __construct(ValidatorInterface $validator, DashboardRepository $dashboard)
    {
        parent::__construct($validator);
        $this->dashboard = $dashboard;
    }

    public function resumen()
    {
        return new JsonResponse($this->dashboard->resumen());
    }
}

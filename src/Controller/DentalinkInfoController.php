<?php

namespace App\Controller;

use App\Service\Dentalink\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DentalinkInfoController extends AbstractController
{
    private $client;

    public function __construct(
        FactoryInterface $factory
    ) {
        $this->client = $factory->create();
    }

    /**
     * @Route("/dentalink/info", name="app_dentalink_info")
     */
    public function index(): Response
    {
        return $this->render('info.twig', [
            'references' => [
                'estados' => $this->client->getStatuses(),
                'dentistas' => $this->client->getDentistas(),
                'tratamientos' => $this->client->getTratamientos(),
                'sucursales' => $this->client->getSucursales(),
                'sillones' => $this->client->getSillones(),
                'especialidades' => $this->client->getEspecialidades(),
            ]
        ]);
    }
}

<?php

namespace App\Controller;

use App\Repository\ScoreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ScoreRepository $scoreRepository): Response
    {

        $topScores = $scoreRepository->findTopScores();


        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'topScores' => $topScores,
        ]);
    }
}

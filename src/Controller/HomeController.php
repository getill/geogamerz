<?php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Repository\ScoreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /**
     * Curated, presentable game ids from the seeded dataset (recognizable
     * titles only — the raw scrape includes a lot of NSFW/obscure noise).
     */
    private const SHOWCASE_GAME_IDS = [54, 60, 68, 55, 53];

    #[Route('/', name: 'app_home')]
    public function index(ScoreRepository $scoreRepository, GameRepository $gameRepository): Response
    {
        $topScores = $scoreRepository->findTopScores();

        $showcaseGames = $gameRepository->findBy(['id' => self::SHOWCASE_GAME_IDS]);
        usort(
            $showcaseGames,
            fn ($a, $b) => array_search($a->getId(), self::SHOWCASE_GAME_IDS, true) <=> array_search($b->getId(), self::SHOWCASE_GAME_IDS, true)
        );

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'topScores' => $topScores,
            'games' => $showcaseGames,
        ]);
    }
}

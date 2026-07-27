<?php

namespace App\Controller;

use App\Entity\Score;
use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GameController extends AbstractController
{
    #[Route('/game', name: 'app_game')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('game/index.html.twig', [
            'controller_name' => 'GameController',
        ]);
    }

    #[Route('/api/score', name: 'api_save_score', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function saveScore(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        // Petite sécurité : on vérifie que la clé "points" existe bien dans ce qu'on a reçu
        if (!isset($data['points'])) {
            // S'il n'y a pas de points, on renvoie une erreur 400 (Bad Request) en format JSON
            return $this->json(['error' => 'Points manquants'], 400);
        }

        $score = new Score();
        $score->setPoints((int) $data['points']);
        $score->setPlayer($user);

        $entityManager->persist($score);
        $entityManager->flush();

        return $this->json(['message' => 'Score enregistré avec succès !'], 201);
    }

    #[Route('/api/games', name: 'api_get_games', methods: ['GET'])]
    public function getGames(EntityManagerInterface $entityManager): JsonResponse
    {
        $games = $entityManager->getRepository(Game::class)->findAll();

        $gamesArray = [];
        foreach ($games as $game) {
            $gamesArray[] = [
                'name' => $game->getName(),
                'publisher' => $game->getPublisher(),
                'imageUrl' => $game->getImageUrl(),
                'releaseYear' => $game->getReleaseYear(),
                'protagonist' => $game->getProtagonist()
            ];
        }

        return $this->json($gamesArray);
    }

}

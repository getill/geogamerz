<?php

namespace App\Service;

use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RawgGameImporter
{
    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'RAWG_API_KEY')]
        private ?string $apiKey,
    ) {
    }

    /**
     * @return array{imported: list<string>, skipped: list<string>}
     */
    public function import(
        ?string $search = null,
        string $ordering = '-rating',
        ?string $dateFrom = '2015-01-01',
        ?string $dateTo = '2023-12-31',
        int $page = 1,
        int $pageSize = 30,
    ): array {
        if (!$this->apiKey) {
            throw new \RuntimeException('La clé RAWG_API_KEY est introuvable.');
        }

        $query = [
            'key' => $this->apiKey,
            'page' => $page,
            'page_size' => $pageSize,
            'ordering' => $ordering,
            'exclude_additions' => true,
        ];

        if ($search) {
            $query['search'] = $search;
        }

        if ($dateFrom && $dateTo) {
            $query['dates'] = sprintf('%s,%s', $dateFrom, $dateTo);
        }

        $response = $this->client->request('GET', 'https://api.rawg.io/api/games', [
            'query' => $query,
        ]);

        $data = $response->toArray();

        $imported = [];
        $skipped = [];

        foreach ($data['results'] as $gameData) {
            $existingGame = $this->entityManager->getRepository(Game::class)->findOneBy(['name' => $gameData['name']]);

            if ($existingGame) {
                $skipped[] = $gameData['name'];
                continue;
            }

            // Requête ciblée pour avoir la fiche complète du jeu (éditeur notamment)
            $gameId = $gameData['id'];
            $detailResponse = $this->client->request('GET', "https://api.rawg.io/api/games/{$gameId}", [
                'query' => ['key' => $this->apiKey],
            ]);
            $detailData = $detailResponse->toArray();

            $game = new Game();
            $game->setName($gameData['name']);
            $game->setImageUrl($gameData['background_image']);

            if (isset($gameData['released'])) {
                $year = (int) substr($gameData['released'], 0, 4);
                $game->setReleaseYear($year);
            }

            $publisherName = 'Inconnu';
            if (!empty($detailData['publishers'])) {
                $publisherName = $detailData['publishers'][0]['name'];
            }
            $game->setPublisher($publisherName);

            // Le protagoniste n'existe pas sur RAWG, laissé vide pour saisie manuelle dans l'admin
            $game->setProtagonist(null);

            $this->entityManager->persist($game);
            $imported[] = $gameData['name'];
        }

        $this->entityManager->flush();

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}

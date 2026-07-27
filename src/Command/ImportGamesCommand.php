<?php
namespace App\Command;

use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import-games',
    description: 'Importe des jeux populaires depuis RAWG',
)]
class ImportGamesCommand extends Command
{
    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // On récupère ta clé secrète depuis le .env.local
        $apiKey = $_SERVER['RAWG_API_KEY'] ?? $_ENV['RAWG_API_KEY'] ?? null;

        if (!$apiKey) {
            $io->error('La clé RAWG_API_KEY est introuvable.');
            return Command::FAILURE;
        }

        $io->title('🚀 Lancement de l\'aspiration depuis RAWG...');

        // On interroge l'API pour récupérer 10 excellents jeux
        $response = $this->client->request('GET', 'https://api.rawg.io/api/games', [
            'query' => [
                'key' => $apiKey,
                'page_size' => 30,
                'ordering' => '-rating', // Les mieux notés
                'dates' => '2015-01-01,2023-12-31',
                'exclude_additions' => true
            ]
        ]);

        $data = $response->toArray();

        foreach ($data['results'] as $gameData) {
            $existingGame = $this->entityManager->getRepository(Game::class)->findOneBy(['name' => $gameData['name']]);

            if ($existingGame) {
                $io->note(sprintf('Le jeu "%s" est déjà dans ta base.', $gameData['name']));
                continue;
            }

            // 🕵️‍♂️ NOUVEAU : On fait une requête ciblée pour avoir la fiche complète du jeu
            $gameId = $gameData['id'];
            $detailResponse = $this->client->request('GET', "https://api.rawg.io/api/games/{$gameId}", [
                'query' => ['key' => $apiKey]
            ]);
            $detailData = $detailResponse->toArray();

            $game = new Game();
            $game->setName($gameData['name']);
            $game->setImageUrl($gameData['background_image']);

            if (isset($gameData['released'])) {
                $year = (int) substr($gameData['released'], 0, 4);
                $game->setReleaseYear($year);
            }

            // 🎯 On fouille dans la fiche détaillée pour extraire le premier éditeur trouvé
            $publisherName = 'Inconnu';
            if (!empty($detailData['publishers'])) {
                $publisherName = $detailData['publishers'][0]['name'];
            }
            $game->setPublisher($publisherName);

            // Le protagoniste n'existe vraiment pas sur RAWG, donc on le laisse vide pour EasyAdmin
            $game->setProtagonist(null);

            $this->entityManager->persist($game);
            $io->success(sprintf('Jeu aspiré avec succès : %s (Éditeur : %s)', $gameData['name'], $publisherName));
        }

        // On sauvegarde tout dans la base de données
        $this->entityManager->flush();

        $io->success('Importation terminée ! Tu as de nouveaux jeux en base.');

        return Command::SUCCESS;
    }
}

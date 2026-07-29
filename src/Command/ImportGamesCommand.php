<?php
namespace App\Command;

use App\Service\RawgGameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-games',
    description: 'Importe des jeux populaires depuis RAWG',
)]
class ImportGamesCommand extends Command
{
    public function __construct(
        private RawgGameImporter $importer,
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Mot-clé de recherche RAWG (nom de jeu)')]
        ?string $search = null,
        #[Option(description: 'Tri RAWG : -rating, -released, -added, -metacritic, name...')]
        string $ordering = '-rating',
        #[Option(name: 'date-from', description: 'Date de sortie minimale (YYYY-MM-DD), défaut : 2015-01-01')]
        ?string $dateFrom = null,
        #[Option(name: 'date-to', description: 'Date de sortie maximale (YYYY-MM-DD), défaut : 2023-12-31')]
        ?string $dateTo = null,
        #[Option(description: 'Numéro de page RAWG (pour paginer et éviter de retomber sur les mêmes jeux)')]
        int $page = 1,
        #[Option(name: 'page-size', description: 'Nombre de résultats à récupérer')]
        int $pageSize = 30,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $io->title('🚀 Lancement de l\'aspiration depuis RAWG...');

        try {
            $result = $this->importer->import(
                $search,
                $ordering,
                $dateFrom ?? '2015-01-01',
                $dateTo ?? '2023-12-31',
                $page,
                $pageSize,
            );
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        foreach ($result['skipped'] as $name) {
            $io->note(sprintf('Le jeu "%s" est déjà dans ta base.', $name));
        }

        foreach ($result['imported'] as $name) {
            $io->success(sprintf('Jeu aspiré avec succès : %s', $name));
        }

        $io->success('Importation terminée ! Tu as de nouveaux jeux en base.');

        return Command::SUCCESS;
    }
}

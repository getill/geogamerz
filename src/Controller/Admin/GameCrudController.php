<?php

namespace App\Controller\Admin;

use App\Entity\Game;
use App\Service\RawgGameImporter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GameCrudController extends AbstractCrudController
{
    private const ORDERING_CHOICES = [
        '-rating' => 'Mieux notés',
        '-added' => 'Popularité (ajouts)',
        '-released' => 'Sortie la plus récente',
        'released' => 'Sortie la plus ancienne',
        '-metacritic' => 'Score Metacritic',
        'name' => 'Nom (A-Z)',
    ];

    public static function getEntityFqcn(): string
    {
        return Game::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $importFromRawg = Action::new('importFromRawg', 'Importer depuis RAWG', 'fas fa-download')
            ->linkToCrudAction('importFromRawg')
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_INDEX, $importFromRawg);
    }

    #[AdminRoute('/import-rawg', name: 'import_rawg')]
    public function importFromRawg(Request $request, RawgGameImporter $importer, AdminUrlGenerator $adminUrlGenerator): Response
    {
        if (!$request->query->getBoolean('do')) {
            return $this->render('admin/game/import_rawg.html.twig', [
                'ordering_choices' => self::ORDERING_CHOICES,
                'defaults' => [
                    'search' => '',
                    'ordering' => '-rating',
                    'date_from' => '2015-01-01',
                    'date_to' => '2023-12-31',
                    'page' => 1,
                    'page_size' => 30,
                ],
            ]);
        }

        $search = $request->query->get('search') ?: null;
        $ordering = $request->query->get('ordering', '-rating');
        $dateFrom = $request->query->get('date_from') ?: null;
        $dateTo = $request->query->get('date_to') ?: null;
        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = max(1, min(40, $request->query->getInt('page_size', 30)));

        $indexUrl = $adminUrlGenerator->setController(self::class)->setAction(Crud::PAGE_INDEX)->generateUrl();

        try {
            $result = $importer->import($search, $ordering, $dateFrom, $dateTo, $page, $pageSize);
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirect($indexUrl);
        }

        if (!empty($result['imported'])) {
            $this->addFlash('success', sprintf('%d jeu(x) importé(s) : %s', count($result['imported']), implode(', ', $result['imported'])));
        }

        if (!empty($result['skipped'])) {
            $this->addFlash('info', sprintf('%d jeu(x) déjà présent(s) ignoré(s).', count($result['skipped'])));
        }

        if (empty($result['imported']) && empty($result['skipped'])) {
            $this->addFlash('info', 'Aucun jeu trouvé chez RAWG pour ces critères.');
        }

        return $this->redirect($indexUrl);
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}

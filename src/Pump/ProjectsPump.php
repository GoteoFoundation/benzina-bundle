<?php

namespace Goteo\BenzinaBundle\Pump;

use App\Entity\Accounting\Accounting;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectStatus;
use App\Entity\Project\ProjectTerritory;
use App\Entity\User\User;
use Goteo\BenzinaBundle\Pump\Trait\ArrayPumpTrait;
use Goteo\BenzinaBundle\Pump\Trait\DoctrinePumpTrait;
use App\Repository\User\UserRepository;
use App\Service\LocalizationService;
use App\Service\Project\TerritoryService;
use Doctrine\ORM\EntityManagerInterface;

class ProjectsPump extends AbstractPump implements PumpInterface
{
    use ArrayPumpTrait;
    use DoctrinePumpTrait;
    use ProjectsPumpTrait;

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private LocalizationService $localizationService,
        private TerritoryService $territoryService,
    ) {}

    public function supports(mixed $batch): bool
    {
        if (!\is_array($batch) || !\array_key_exists(0, $batch)) {
            return false;
        }

        return $this->hasAllKeys($batch[0], self::PROJECT_KEYS);
    }

    public function pump(mixed $batch): void
    {
        $batch = $this->skipPumped($batch, 'id', Project::class, 'migratedId');

        $owners = $this->getOwners($batch);

        foreach ($batch as $key => $record) {
            if (!$this->isPumpable($record)) {
                continue;
            }

            if (!\array_key_exists($record['owner'], $owners)) {
                continue;
            }

            $project = new Project();
            $project->setTranslatableLocale($this->getProjectLang($record['lang']));
            $project->setTitle($record['name']);
            $project->setSubtitle($record['subtitle']);
            $project->setTerritory($this->getProjectTerritory($record));
            $project->setDescription($record['description']);
            $project->setOwner($owners[$record['owner']]);
            $project->setStatus($this->getProjectStatus($record['status']));
            $project->setMigrated(true);
            $project->setMigratedId($record['id']);
            $project->setDateCreated(new \DateTime($record['created']));
            $project->setDateUpdated(new \DateTime());

            $this->entityManager->persist($project);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function isPumpable(array $record): bool
    {
        if (
            empty($record['id'])
            || empty($record['name'])
            || empty($record['description'])
            || \in_array($record['status'], [0, 1])
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return User[]
     */
    private function getOwners(array $record): array
    {
        $users = $this->userRepository->findBy(['migratedId' => \array_map(function ($record) {
            return $record['owner'];
        }, $record)]);

        $owners = [];
        foreach ($users as $user) {
            $owners[$user->getMigratedId()] = $user;
        }

        return $owners;
    }

    private function getProjectLang(string $lang): string
    {
        if (empty($lang)) {
            return $this->localizationService->getDefaultLanguage();
        }

        return $this->localizationService->getLanguage($lang);
    }

    private function getProjectTerritory(array $record): ProjectTerritory
    {
        if (empty($record['country'])) {
            return ProjectTerritory::unknown();
        }

        if (!empty($record['project_location'])) {
            $cleanLocation = self::cleanProjectLocation($record['project_location'], 2);

            if ($cleanLocation !== '') {
                return $this->territoryService->search($cleanLocation);
            }
        }

        return $this->territoryService->search($record['country']);
    }

    private function getProjectStatus(int $status): ProjectStatus
    {
        switch ($status) {
            case 1:
                return ProjectStatus::InEditing;
            case 2:
                return ProjectStatus::InReview;
            case 0:
                return ProjectStatus::Rejected;
            case 3:
                return ProjectStatus::InCampaign;
            case 6:
                return ProjectStatus::Unfunded;
            case 4:
                return ProjectStatus::InFunding;
            case 5:
                return ProjectStatus::Fulfilled;
        }
    }

    private function getAccounting(array $record): Accounting
    {
        $accounting = new Accounting();
        $accounting->setCurrency($record['currency']);

        return $accounting;
    }
}
<?php

namespace Keiwen\Competition\Builder;

use Keiwen\Competition\CompetitionTreePhase;
use Keiwen\Competition\Exception\CompetitionBuilderOptionException;
use Keiwen\Competition\Exception\CompetitionPlayerCountException;
use Keiwen\Competition\Exception\CompetitionTypeException;
use Keiwen\Utils\Mutator\ArrayMutator;

class CompetitionBuilderPhase
{
    /** @var string */
    protected $name = '';
    /** @var string */
    protected $dispatchMethod = '';
    /** @var CompetitionBuilder[] */
    protected $builderGroups = array();
    /** @var CompetitionPlayerSelector[] $playerSelectorsInTree */
    protected $playerSelectorsInTree = array();

    const DISPATCH_METHOD_DEAL = 'deal';
    const DISPATCH_METHOD_RANDOM = 'random';


    public function __construct(string $name, string $dispatchMethod = '')
    {
        $this->name = $name;
        if (!in_array($dispatchMethod, static::getDispatchMethods())) $dispatchMethod = static::DISPATCH_METHOD_DEAL;
        $this->dispatchMethod = $dispatchMethod;
    }

    /**
     * @return string[]
     */
    public static function getDispatchMethods(): array
    {
        return array(
            self::DISPATCH_METHOD_DEAL,
            self::DISPATCH_METHOD_RANDOM,
        );
    }


    /**
     * @param string $type
     * @param array $options
     * @param string $name
     * @return bool
     * @throws CompetitionBuilderOptionException
     * @throws CompetitionTypeException
     */
    public function addGroup(string $type, array $options = array(), string $name = ''): bool
    {
        if (empty($name)) $name = (string) count($this->builderGroups);
        $builder = new CompetitionBuilder($type, $options);
        return $this->addGroupFromBuilder($builder, $name);
    }


    public function addGroupFromBuilder(CompetitionBuilder $builder, string $name = ''): bool
    {
        $cloned = clone $builder;
        $cloned->setName($name);
        $this->builderGroups[$name] = $cloned;
        return true;
    }

    public function getGroup(string $name): ?CompetitionBuilder
    {
        return $this->builderGroups[$name] ?? null;
    }

    /**
     * @return CompetitionBuilder[]
     */
    public function getGroups(): array
    {
        return $this->builderGroups;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDispatchMethod(): string
    {
        return $this->dispatchMethod;
    }

    public function setDispatchMethod(string $dispatchMethod): self
    {
        if (in_array($dispatchMethod, static::getDispatchMethods())) $this->dispatchMethod = $dispatchMethod;
        return $this;
    }


    /**
     * @return CompetitionPlayerSelector[]
     */
    public function getPlayerSelectors(): array
    {
        return $this->playerSelectorsInTree;
    }


    public function resetPlayerSelectors(): self
    {
        $this->playerSelectorsInTree = array();
        return $this;
    }

    /**
     * @param CompetitionPlayerSelector $selector
     * @return $this
     */
    public function addPlayerSelector(CompetitionPlayerSelector $selector): self
    {
        $this->playerSelectorsInTree[] = $selector;
        return $this;
    }


    /**
     * @param array $players
     * @param string $playerEloAccess method to access ELO in object or field name to access elo in array (leave empty if ELO is not used)
     * @param array $teamComposition $teamKey => list of players keys
     * @return CompetitionTreePhase|null
     * @throws CompetitionPlayerCountException
     */
    public function startPhase(array $players, string $playerEloAccess = '', array $teamComposition = array()): ?CompetitionTreePhase
    {
        if (empty($this->builderGroups)) return null;

        $computedMinPlayers = $this->computeMinPlayersCount();
        if (count($players) < $computedMinPlayers) {
            throw new CompetitionPlayerCountException('to start phase', $computedMinPlayers);
        }

        $playersDispatch = $this->dispatchPlayers($players);

        $groupCount = 0;
        $competitions = array();
        foreach ($this->builderGroups as $name => $builder) {
            $competition = $builder->buildForPlayers($playersDispatch[$groupCount], $playerEloAccess, $teamComposition);
            $competitions[$name] = $competition;
            $groupCount++;
        }

        return new CompetitionTreePhase($this->getName(), $competitions);
    }


    protected function dispatchPlayers(array $players): array
    {
        $groupCount = count($this->builderGroups);
        $dispatch = array();
        $playersListMutator = new ArrayMutator($players);
        if ($this->dispatchMethod == static::DISPATCH_METHOD_RANDOM) $players = $playersListMutator->shufflePreservingKeys();
        switch ($this->dispatchMethod) {
            case static::DISPATCH_METHOD_DEAL:
            case static::DISPATCH_METHOD_RANDOM:
            default:
                $dispatch = $playersListMutator->deal($groupCount);
                break;
        }
        return $dispatch;
    }


    /**
     * compute minimum players count needed to build phase
     * (= sum of minimum number of players in each group of this phase)
     * @return int
     */
    public function computeMinPlayersCount(): int
    {
        $sum = 0;
        foreach ($this->getGroups() as $group) {
            $sum += $group->getMinPlayersRequired();
        }
        return $sum;
    }


    public function getQualificationSpots(): int
    {
        $sum = 0;
        foreach ($this->getGroups() as $group) {
            $sum += $group->getQualificationSpots();
        }
        return $sum;
    }

    public function getEliminationSpots(): int
    {
        $sum = 0;
        foreach ($this->getGroups() as $group) {
            $sum += $group->getEliminationSpots();
        }
        return $sum;
    }

}

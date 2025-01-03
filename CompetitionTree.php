<?php

namespace Keiwen\Competition;

use Keiwen\Competition\Builder\CompetitionBuilderTree;
use Keiwen\Competition\Builder\CompetitionBuilderPhase;
use Keiwen\Competition\Builder\CompetitionPlayerSelector;
use Keiwen\Competition\Exception\CompetitionPlayerCountException;
use Keiwen\Competition\Exception\CompetitionRankingException;
use Keiwen\Competition\Ranking\AbstractRanking;

class CompetitionTree
{

    /** @var CompetitionBuilderTree */
    protected $builderTree;
    /** @var string */
    protected $iterationName = '';
    /** @var CompetitionTreePhase[] */
    protected $phases = array();
    protected $completed = false;
    protected $players = array();
    protected $unusedPlayers = array();
    protected $lastPhaseNameCompleted;
    protected $playerEloAccess = '';
    protected $teamComposition = array();

    /**
     * @param CompetitionBuilderTree $builderTree
     * @param array $players
     * @param string $iterationName
     * @param string $playerEloAccess method to access ELO in object or field name to access elo in array (leave empty if ELO is not used)
     * @param array $teamComposition $teamKey => list of players keys
     * @throws CompetitionPlayerCountException
     */
    public function __construct(CompetitionBuilderTree $builderTree, array $players, string $iterationName = '', string $playerEloAccess = '', array $teamComposition = array())
    {
        $this->builderTree = $builderTree;
        $this->players = $players;
        $this->unusedPlayers = $players;
        $this->iterationName = $iterationName;
        $this->playerEloAccess = $playerEloAccess;
        $this->teamComposition = $teamComposition;

        $builderPhases = $builderTree->getPhases();
        $firstPhase = reset($builderPhases);
        $this->startPhaseInTree($firstPhase);
    }


    public function getName(): string
    {
        if (empty($this->iterationName)) return $this->builderTree->getName();
        return $this->builderTree->getName() . ' ' . $this->iterationName;
    }

    public function getIterationName(): string
    {
        return $this->iterationName;
    }

    public function getBuilderTree(): CompetitionBuilderTree
    {
        return $this->builderTree;
    }


    public function getPhase(string $name): ?CompetitionTreePhase
    {
        return $this->phases[$name] ?? null;
    }

    /**
     * @return CompetitionTreePhase[]
     */
    public function getPhases(): array
    {
        return $this->phases;
    }


    /**
     * @return bool
     * @throws CompetitionPlayerCountException
     */
    public function isCompleted(): bool
    {
        if ($this->completed) return true;
        $currentPhase = $this->getCurrentPhase();
        return empty($currentPhase);
    }


    /**
     * @return CompetitionTreePhase|null
     * @throws CompetitionPlayerCountException
     */
    public function getCurrentPhase(): ?CompetitionTreePhase
    {
        if ($this->completed) return null;
        $phaseCandidate = null;
        foreach ($this->phases as $phaseName => $phase) {
            if (!$phase->isCompleted()) {
                $phaseCandidate = $phase;
                break;
            }
            $this->lastPhaseNameCompleted = $phaseName;
        }
        if ($phaseCandidate === null) {
            // last phase is completed, check if we need another
            $nextBuilderPhase = $this->builderTree->getPhaseAfter($this->lastPhaseNameCompleted);
            if (empty($nextBuilderPhase)) {
                // actually it's all completed!
                $this->completed = true;
                return null;
            }

            return $this->startPhaseInTree($nextBuilderPhase);
        }

        return $phaseCandidate;
    }

    public function getLastPhase(): ?CompetitionTreePhase
    {
        $lastPhase = end($this->phases);
        return $lastPhase;
    }


    /**
     * @param CompetitionBuilderPhase $builderPhase
     * @return CompetitionTreePhase
     * @throws CompetitionPlayerCountException
     */
    protected function startPhaseInTree(CompetitionBuilderPhase $builderPhase): CompetitionTreePhase
    {
        // get players for next phase
        $playerKeys = $this->computePlayersKeysForPhase($builderPhase);
        $playersForPhase = array();
        foreach ($playerKeys as $playerKey) {
            // remove these for unused and list players
            unset($this->unusedPlayers[$playerKey]);

            $playersForPhase[$playerKey] = $this->players[$playerKey];
        }
        // start new phase
        $phase = $builderPhase->startPhase($playersForPhase, $this->playerEloAccess, $this->teamComposition);
        $this->phases[$builderPhase->getName()] = $phase;
        return $phase;
    }

    public function getPlayerEloAccess(): string
    {
        return $this->playerEloAccess;
    }

    public function isUsingElo(): bool
    {
        return !empty($this->playerEloAccess);
    }

    /**
     * @return array $teamKey => list of players keys
     */
    public function getTeamComposition(): array
    {
        return $this->teamComposition;
    }

    public function isUsingTeam(): bool
    {
        return !empty($this->teamComposition);
    }

    public function getPlayers(): array
    {
        return $this->players;
    }

    /**
     * @param int|string $playerKey
     * @return mixed|null if found, player data
     */
    public function getPlayer($playerKey)
    {
        return $this->players[$playerKey] ?? null;
    }

    /**
     * @param CompetitionBuilderPhase $builderPhase
     * @return array
     */
    protected function computePlayersKeysForPhase(CompetitionBuilderPhase $builderPhase): array
    {
        $playersKeys = array();

        $selectors = $builderPhase->getPlayerSelectors();
        // if no selectors given, set a default empty selector
        if (empty($selectors)) $selectors = array(new CompetitionPlayerSelector());

        foreach ($selectors as $selector) {
            // get base phase
            $phase = null;
            // try to get phase with given name
            $phase = $this->getPhase($selector->getPhaseName());
            if (empty($phase) && $this->lastPhaseNameCompleted !== null) {
                // if not found, try to get last phase completed
                $phase = $this->getPhase($this->lastPhaseNameCompleted);
            }

            // get base pack
            if (empty($phase)) {
                // no specific phase found, we should use list of unused players
                $packKeys = array_keys($this->unusedPlayers);
            } else {
                // set parameters according to pickup method
                $pickupByRank = false;
                $pickupPhaseRanked = false;
                $pickupByRankShuffled = true;
                switch ($selector->getPickupMethod()) {
                    case CompetitionPlayerSelector::PICKUP_METHOD_BYRANKINGROUP:
                        $pickupByRank = true;
                        break;
                    case CompetitionPlayerSelector::PICKUP_METHOD_BYRANKSHUFFLED:
                        $pickupByRank = true;
                        $pickupByRankShuffled = true;
                        break;
                    case CompetitionPlayerSelector::PICKUP_METHOD_BYRANKINPHASE:
                        $pickupPhaseRanked = true;
                        break;
                    case CompetitionPlayerSelector::PICKUP_METHOD_BYGROUP:
                    default:
                        // don't change it's OK
                }

                // get a pack
                switch ($selector->getPlayerPackName()) {
                    case CompetitionBuilderTree::PLAYER_PACK_QUALIFIED:
                        // players in qualification spot for given phase
                        $packKeys = $phase->getPlayerKeysForQualification($pickupByRank, $pickupPhaseRanked, $pickupByRankShuffled);
                        break;
                    case CompetitionBuilderTree::PLAYER_PACK_STAGNATION:
                        // players in stagnation spot (neither qualified nor eliminated) for given phase
                        $packKeys = $phase->getPlayerKeysForStagnation($pickupByRank, $pickupPhaseRanked, $pickupByRankShuffled);
                        break;
                    case CompetitionBuilderTree::PLAYER_PACK_UNUSED:
                        // players that did not participate in any phase yet
                        $packKeys = array_keys($this->unusedPlayers);
                        break;
                    default:
                        // combination of unused players and previously qualified players
                        $packKeys = array_merge(array_keys($this->unusedPlayers), $phase->getPlayerKeysForQualification($pickupByRank, $pickupPhaseRanked, $pickupByRankShuffled));
                        break;
                }
            }

            // if selection limit given, slice in keys received
            // if start is too high, note that nothing is returned
            if (($selector->getStartPickAtRank() !== 1) || ($selector->getSelectionLength() !== 0)) {
                $selectionLength = $selector->getSelectionLength();
                // if length = 0, convert to null for slice method
                if ($selectionLength == 0) $selectionLength = null;
                $selectedKeys = array_slice($packKeys, $selector->getStartPickAtRank() - 1, $selectionLength);
            } else {
                $selectedKeys = $packKeys;
            }

            $playersKeys = array_merge($playersKeys, $selectedKeys);
        }

        return array_unique($playersKeys);
    }


    /**
     * @param bool $forTeams
     * @param bool $mixGroups
     * @param bool $byExpenses
     * @return string[] entityKey => name of the last phase reach
     */
    protected function getRankedEntityKeys(bool $forTeams = false, bool $mixGroups = true, bool $byExpenses = false): array
    {
        $ranked = array();
        $entities = ($forTeams) ? $this->getTeamComposition() : $this->getPlayers();
        // entities array is entity key => entity value, we'll just check if entity key is set we'll not use value
        $phases = $this->getPhases();
        // reverse phase order to consider the latest phases first
        $phases = array_reverse($phases);
        foreach ($phases as $phaseName => $phase) {
            if ($mixGroups) {
                try {
                    $phaseMixRankings = ($forTeams) ? $phase->getMixedTeamRankings() : $phase->getMixedRankings($byExpenses);
                } catch (CompetitionRankingException $e) {
                    // ignore issue with mixed rankings in here
                    $phaseMixRankings = array();
                }
                foreach ($phaseMixRankings as $ranking) {
                    /** @var AbstractRanking $ranking */
                    $entityKey = $ranking->getEntityKey();
                    // take first ranking found, ignore potential others
                    if (isset($entities[$entityKey])) {
                        $ranked[$entityKey] = $phaseName;
                        unset($entities[$entityKey]);
                    }
                }
            } else {
                $phaseRankings = ($forTeams) ? $phase->getTeamRankings() : $phase->getRankings($byExpenses);
                foreach ($phaseRankings as $groupRankings) {
                    foreach ($groupRankings as $ranking) {
                        /** @var AbstractRanking $ranking */
                        $entityKey = $ranking->getEntityKey();
                        // take first ranking found, ignore potential others
                        if (isset($entities[$entityKey])) {
                            $ranked[$entityKey] = $phaseName;
                            unset($entities[$entityKey]);
                        }
                    }
                }
            }

            // if all entities found, ignore previous phases that should come next in this loop
            if (empty($entities)) break;
        }

        // we may have entities left: these should be on top as planed for future phases that did not occurs yet
        if (!empty($entities)) {
            $leftEntities = array();
            foreach (array_keys($entities) as $entityKey) {
                $leftEntities[$entityKey] = null;
            }
            $ranked = array_merge($leftEntities, $ranked);
        }

        return $ranked;
    }



    /**
     * @param bool $mixGroups
     * @param bool $byExpenses
     * @return string[] playerKey => name of the last phase reach
     */
    public function getRankedPlayerKeys(bool $mixGroups = true, bool $byExpenses = false): array
    {
        return $this->getRankedEntityKeys(false, $mixGroups, $byExpenses);
    }


    /**
     * @param bool $mixGroups
     * @return string[] teamKey => name of the last phase reach
     */
    public function getRankedTeamKeys(bool $mixGroups = true): array
    {
        return $this->getRankedEntityKeys(true, $mixGroups);
    }



}

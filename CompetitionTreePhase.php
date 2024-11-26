<?php

namespace Keiwen\Competition;


use Keiwen\Competition\Exception\CompetitionRankingException;
use Keiwen\Competition\Game\AbstractGame;
use Keiwen\Competition\Ranking\AbstractRanking;
use Keiwen\Competition\Type\AbstractCompetition;

class CompetitionTreePhase
{
    /** @var string */
    protected $name = '';
    /** @var AbstractCompetition[] */
    protected $groups = array();
    protected $completed = false;


    /**
     * @param AbstractCompetition[] $groups
     */
    public function __construct(string $name, array $groups)
    {
        $this->name = $name;

        foreach ($groups as $name => $group) {
            if ($group instanceof AbstractCompetition) $this->groups[$name] = $group;
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return AbstractCompetition[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getGroup(string $name): ?AbstractCompetition
    {
        return $this->groups[$name] ?? null;
    }

    public function isCompleted(): bool
    {
        if ($this->completed) return true;
        $nextGame = $this->getNextGame();
        return empty($nextGame);
    }


    public function getCurrentRound(): int
    {
        if ($this->completed) return -1;
        $nextGame = $this->getNextGame();
        if (empty($nextGame)) return -1;
        return $nextGame->getCompetitionRound();
    }


    /**
     * @param string|null $groupName if next game found, set with group name
     * @return AbstractGame|null
     */
    public function getNextGame(string &$groupName = null): ?AbstractGame
    {
        if ($this->completed) return null;
        $nextRound = -1;
        $nextGameCandidate = null;
        foreach ($this->groups as $name => $group) {
            $nextGameInGroup = $group->getNextGame();
            if (empty($nextGameInGroup)) continue;
            if ($nextRound == -1 || $nextRound > $nextGameInGroup->getCompetitionRound()) {
                $nextRound = $nextGameInGroup->getCompetitionRound();
                $nextGameCandidate = $nextGameInGroup;
                $groupName = $name;
            }
        }
        if ($nextGameCandidate === null) {
            // actually it's all completed!
            $this->completed = true;
        }
        return $nextGameCandidate;
    }

    /**
     * @param int $round
     * @return AbstractGame[]
     */
    public function getGamesByRound(int $round): array
    {
        $games = array();
        foreach ($this->groups as $group) {
            $roundGamesInGroup = $group->getGamesByRound($round);
            $games = array_merge($games, $roundGamesInGroup);
        }
        return $games;
    }

    public function getGameCount(): int
    {
        $sum = 0;
        foreach ($this->groups as $group) {
            $sum += $group->getGameCount();
        }
        return $sum;
    }

    public function getGamesCompletedCount(): int
    {
        $sum = 0;
        foreach ($this->groups as $group) {
            $sum += $group->getGamesCompletedCount();
        }
        return $sum;
    }

    public function getGamesToPlayCount(): int
    {
        $gameCount = $this->getGameCount();
        if ($gameCount) return 0;
        return $gameCount - $this->getGamesCompletedCount();
    }

    public function getRoundCount(): int
    {
        $sum = 0;
        foreach ($this->groups as $group) {
            $sum += $group->getRoundCount();
        }
        return $sum;
    }


    public function getRankings(bool $byExpenses = false): array
    {
        $rankings = array();
        foreach ($this->groups as $name => $group) {
            $rankings[$name] = $group->getRankings($byExpenses);
        }
        return $rankings;
    }

    public function getTeamRankings(): array
    {
        $rankings = array();
        foreach ($this->groups as $name => $group) {
            $rankings[$name] = $group->getTeamRankings();
        }
        return $rankings;
    }


    /**
     * @param bool $forTeam false by default
     * @param bool $byExpenses
     * @return AbstractRanking[]
     * @throws CompetitionRankingException
     */
    protected function mixGroupRankings(bool $forTeam = false, bool $byExpenses = false): array
    {
        $allRankings = ($forTeam) ? $this->getTeamRankings() : $this->getRankings();
        if (empty($allRankings)) return array();
        $firstGroupRankings = reset($allRankings);
        $firstGroupName = array_key_first($allRankings);
        if (empty($firstGroupRankings)) return array();
        /** @var AbstractRanking $firstRanking */
        $firstRanking = reset($firstGroupRankings);
        if (empty($firstRanking)) return array();
        $firstGroup = $this->getGroup($firstGroupName);
        if (empty($firstGroup)) return array();
        $rankingHolder = $firstGroup->getRankingsHolder();
        $mixedRankingHolder = $rankingHolder->duplicateEmptyHolder();
        try {
            foreach ($allRankings as $groupRankings) {
                foreach ($groupRankings as $ranking) {
                    /** @var AbstractRanking $ranking */
                    $mixedRanking = clone $ranking;
                    $mixedRankingHolder->integrateRanking($mixedRanking);
                }
            }
        } catch (CompetitionRankingException $e) {
            throw new CompetitionRankingException(sprintf('Cannot build mixed rankings: %s', $e->getMessage()));
        }

        $mixedRankingHolder->computeRankingsOrder();
        // even for team we need to use getRankings here and not teamRankings
        return ($byExpenses && !$forTeam) ? $mixedRankingHolder->getRankingsByExpenses() : $mixedRankingHolder->getRankings();
    }



    /**
     * @param bool $byExpenses
     * @return AbstractRanking[]
     * @throws CompetitionRankingException
     */
    public function getMixedRankings(bool $byExpenses = false): array
    {
        return $this->mixGroupRankings(false, $byExpenses);
    }



    /**
     * @param string[]|int[] $keys
     * @param bool $byExpenses
     * @return AbstractRanking[]
     * @throws CompetitionRankingException
     */
    public function getMixedRankingsForKeys(array $keys, bool $byExpenses = false): array
    {
        $filtered = array();
        $allMixed = $this->getMixedRankings($byExpenses);
        foreach ($allMixed as $ranking) {
            if (in_array($ranking->getEntityKey(), $keys)) {
                $filtered[] = $ranking;
            }
        }
        return $filtered;
    }


    /**
     * @return AbstractRanking[]
     * @throws CompetitionRankingException
     */
    public function getMixedRankingsForQualification(): array
    {
        return $this->getMixedRankingsForKeys($this->getPlayerKeysForQualification());
    }

    /**
     * @return AbstractRanking[]
     * @throws CompetitionRankingException
     */
    public function getMixedRankingsForStagnation(): array
    {
        return $this->getMixedRankingsForKeys($this->getPlayerKeysForStagnation());
    }

    /**
     * @return AbstractRanking[]
     * @throws CompetitionRankingException
     */
    public function getMixedRankingsForElimination(): array
    {
        return $this->getMixedRankingsForKeys($this->getPlayerKeysForElimination());
    }



    /**
     * @return AbstractRanking[]
     * @throws CompetitionRankingException
     */
    public function getMixedTeamRankings(): array
    {
        return $this->mixGroupRankings(true);
    }


    /**
     * By default, you will have all players from 1st group, then all players for 2nd group, ...
     * @param bool $byRank set true to have all 1st players form all groups, then all 2nd players from all groups, ...
     * @param bool $phaseRanked set true to mix all rankings across any group and re-order accordingly
     * @param bool $shuffleByRank set true while using byRank so shuffle all players than finished 1st, then shuffle all players 2nd, ...
     * @return int[]|string[]
     */
    public function getPlayerKeysForQualification(bool $byRank = false, bool $phaseRanked = false, bool $shuffleByRank = false): array
    {
        return $this->getPlayerKeysForSpot('qualification', $byRank, $phaseRanked, $shuffleByRank);
    }

    /**
     * By default, you will have all players from 1st group, then all players for 2nd group, ...
     * @param bool $byRank set true to have all 1st players form all groups, then all 2nd players from all groups, ...
     * @param bool $phaseRanked set true to mix all rankings across any group and re-order accordingly
     * @param bool $shuffleByRank set true while using byRank so shuffle all players than finished 1st, then shuffle all players 2nd, ...
     * @return int[]|string[]
     */
    public function getPlayerKeysForStagnation(bool $byRank = false, bool $phaseRanked = false, bool $shuffleByRank = false): array
    {
        return $this->getPlayerKeysForSpot('stagnation', $byRank, $phaseRanked, $shuffleByRank);
    }

    /**
     * By default, you will have all players from 1st group, then all players for 2nd group, ...
     * @param bool $byRank set true to have all 1st players form all groups, then all 2nd players from all groups, ...
     * @param bool $phaseRanked set true to mix all rankings across any group and re-order accordingly
     * @param bool $shuffleByRank set true while using byRank so shuffle all players than finished 1st, then shuffle all players 2nd, ...
     * @return int[]|string[]
     */
    public function getPlayerKeysForElimination(bool $byRank = false, bool $phaseRanked = false, bool $shuffleByRank = false): array
    {
        return $this->getPlayerKeysForSpot('elimination', $byRank, $phaseRanked, $shuffleByRank);
    }


    /**
     * @param string $spotType
     * @param bool $byRank
     * @param bool $phaseRanked
     * @return int[]|string[]
     */
    protected function getPlayerKeysForSpot(string $spotType, bool $byRank = false, bool $phaseRanked = false, bool $shuffleByRank = false): array
    {
        $playerKeys = array();
        $playerKeysByRank = array();
        foreach ($this->groups as $group) {

            // get corresponding player in matching spot for a group
            switch ($spotType) {
                case 'qualification': $groupSpots = $group->getPlayerKeysForQualification(); break;
                case 'stagnation': $groupSpots = $group->getPlayerKeysForStagnation(); break;
                case 'elimination': $groupSpots = $group->getPlayerKeysForElimination(); break;
            }

            if ($byRank) {
                // store each in a given rank (we do not care about actual rank value though)
                foreach ($groupSpots as $index => $playerKey) {
                    if (!isset($playerKeysByRank[$index])) $playerKeysByRank[$index] = array();
                    $playerKeysByRank[$index][] = $playerKey;
                }
            } else {
                // add player group by group
                $playerKeys = array_merge($playerKeys, $groupSpots);
            }
        }

        if ($byRank) {
            // loop again on each rank and add rank by rank
            foreach ($playerKeysByRank as $index => $keysInRank) {
                // shuffle the players in this rank if needed
                if ($shuffleByRank) shuffle($keysInRank);
                $playerKeys = array_merge($playerKeys, $keysInRank);
            }
        }

        if ($phaseRanked) {
            // mix all rankings if possible
            try {
                $rankings = $this->getMixedRankingsForKeys($playerKeys);
                $playerKeys = array();
                foreach ($rankings as $ranking) {
                    $playerKeys[] = $ranking->getEntityKey();
                }
            } catch (CompetitionRankingException $e) {
                // cancel mix rankings if issue with it
            }
        }

        return $playerKeys;
    }

}

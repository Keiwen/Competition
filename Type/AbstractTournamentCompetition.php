<?php
namespace Keiwen\Competition\Type;

use Keiwen\Competition\Exception\CompetitionRankingException;
use Keiwen\Competition\Exception\CompetitionRuntimeException;
use Keiwen\Competition\Game\AbstractGame;
use Keiwen\Competition\Game\GameDuel;
use Keiwen\Competition\Ranking\RankingDuel;
use Keiwen\Competition\Ranking\RankingsHolder;
use Keiwen\Utils\Math\Divisibility;

abstract class AbstractTournamentCompetition extends AbstractCompetition
{
    protected $bestSeedAlwaysHome = false;
    protected $preRoundShuffle = false;

    /** @var GameDuel[] $gameRepository */
    protected $gameRepository = array();


    public function isBestSeedAlwaysHome(): bool
    {
        return $this->bestSeedAlwaysHome;
    }

    public function hasPreRoundShuffle(): bool
    {
        return $this->preRoundShuffle;
    }


    /**
     * @param int $numberOfPlayers
     * @param int $round
     * @return void
     * @throws CompetitionRuntimeException
     */
    protected function checkPowerOf2NumberOfPlayer(int $numberOfPlayers, int $round)
    {
        $remainder = 0;
        Divisibility::getHighestPowerOf($numberOfPlayers, 2, $remainder);
        if ($remainder > 0) {
            throw new CompetitionRuntimeException(sprintf('Cannot create next round with a number of players that is not a power of 2, %d given on round %d', $numberOfPlayers, $round));
        }
    }

    /**
     * Considering number of players given, dispatch duels so that first seeds
     * encounters last seeds. Furthermore, dispatch first seeds among this table
     * @param int $playersCount
     * @return array list of duel (array with 'seedHome' and 'seedAway' keys)
     * @throws CompetitionRuntimeException
     */
    protected function generateDuelTable(int $playersCount): array
    {
        $this->checkPowerOf2NumberOfPlayer($playersCount, 1);

        // for each player in first half, match with last player available
        // should be X (number of player) + 1 - first player seed
        // for 8 players, we will have
        // 1vs8, 2vs7, 3vs6, 4vs5
        // note: do not add games yet because of next step
        $duelTable = array();
        for ($i = 1; $i <= $playersCount / 2; $i++) {
            $duelTable[$i - 1][] = array(
                'seedHome' => $i,
                'seedAway' => $playersCount + 1 - $i,
            );
        }
        // now we want to avoid duel between high seeds until the end
        // to dispatch, each duel are set in a table part.
        // while this table has more than 1 part,
        // second half of parts are put in first half (in reversed order)
        // for 8 players, we started with 4 parts
        // first iteration will give
        // PART1, PART2
        // 1vs8, 2vs7
        // 4vs5, 3vs6 (not 3vs6 and 4vs5, as we reversed)
        // 2nd iteration will give
        // PART1
        // 1vs8
        // 4vs5
        // 2vs7
        // 3vs6
        // note: we always have halves in parts because number of player is power of 2
        while (count($duelTable) > 1) {
            $partCount = count($duelTable);
            for ($i = $partCount / 2; $i < $partCount; $i++) {
                $firstHalfPart = $partCount - $i - 1;
                $duelTable[$firstHalfPart] = array_merge($duelTable[$firstHalfPart], $duelTable[$i]);
                unset($duelTable[$i]);
            }
        }

        // now that all are dispatched, return first and only part
        $duelTable = reset($duelTable);
        return $duelTable;
    }


    /**
     * @param int $round
     * @param array $losers
     * @return int[]|string[]
     */
    public function getRoundWinners(int $round, array &$losers = array()): array
    {
        $gamesInRound = $this->getGamesByRound($round);
        $winnerKeys = array();
        $losers = array();
        foreach ($gamesInRound as $game) {
            if (!$game->isPlayed()) continue;
            $winnerKeys[] = $game->hasAwayWon() ? $game->getKeyAway() : $game->getKeyHome();
            // we should not have drawn on tournament
            // but if drawn set, we consider that home won
            $loserKey = $game->hasAwayWon() ? $game->getKeyHome() : $game->getKeyAway();
            // ignore loser if null => bye game
            if ($loserKey !== null && $loserKey !== '') $losers[] = $loserKey;
        }
        return $winnerKeys;
    }


    /**
     * add games by matching given players 2 by 2, in received order
     *
     * @param array $playerKeys
     * @throws CompetitionRuntimeException
     */
    protected function matchPlayers2By2(array $playerKeys)
    {
        $playerKeys = array_values($playerKeys);
        for ($i = 0; $i < count($playerKeys); $i += 2) {
            $this->addGame($this->currentRound, $playerKeys[$i], $playerKeys[$i + 1]);
        }
    }


    public function getMinGameCountByPlayer(): int
    {
        return 1;
    }

    public static function getMinPlayerCount(): int
    {
        return 4;
    }


    /**
     * @return RankingsHolder
     * @throws CompetitionRankingException
     */
    protected function initializeRankingsHolder(): RankingsHolder
    {
        return RankingDuel::generateDefaultRankingsHolder();
    }


    /**
     * get games for given round
     * @param int $round
     * @return GameDuel[] games of the round
     */
    public function getGamesByRound(int $round): array
    {
        return parent::getGamesByRound($round);
    }

    /**
     * get game with a given number
     * @param int $gameNumber
     * @return GameDuel|null game if found
     */
    public function getGameByNumber(int $gameNumber): ?AbstractGame
    {
        return parent::getGameByNumber($gameNumber);
    }

    /**
     * @return GameDuel[]
     */
    public function getGames(): array
    {
        return parent::getGames();
    }

    /**
     * @param int $round
     * @param int|string $keyHome
     * @param int|string $keyAway
     * @return GameDuel
     * @throws CompetitionRuntimeException
     */
    protected function addGame(int $round, $keyHome = 1, $keyAway = 2): AbstractGame
    {
        if ($keyAway !== null && $this->isBestSeedAlwaysHome()) {
            $seedHome = $this->getPlayerSeed($keyHome);
            $seedAway = $this->getPlayerSeed($keyAway);
            if ($seedAway < $seedHome) {
                $tempKey = $keyAway;
                $keyAway = $keyHome;
                $keyHome = $tempKey;
            }
        }
        $gameDuel = new GameDuel($keyHome, $keyAway);
        $gameDuel->setCompetitionRound($round);
        $this->calendar[$round][] = $gameDuel;
        return $gameDuel;
    }

    /**
     * @param GameDuel $game
     */
    protected function updateRankingsForGame($game)
    {
        $rankingHome = $this->rankingsHolder->getRanking($game->getKeyHome());
        if ($rankingHome) {
            $rankingHome->saveGame($game);
        }
        $rankingAway = $this->rankingsHolder->getRanking($game->getKeyAway());
        if ($rankingAway) {
            $rankingAway->saveGame($game);
        }
    }

    public function getMaxPointForAGame(): int
    {
        $rankings = $this->rankingsHolder->getAllRankings();
        $firstRanking = reset($rankings);
        if (empty($firstRanking)) return -1;
        return $firstRanking->getPointsForWon(true);
    }


    public function getMinPointForAGame(): int
    {
        $rankings = $this->rankingsHolder->getAllRankings();
        $firstRanking = reset($rankings);
        if (empty($firstRanking)) return 0;
        return $firstRanking->getPointsForLoss(true);
    }



}

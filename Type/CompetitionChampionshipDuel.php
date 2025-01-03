<?php

namespace Keiwen\Competition\Type;

use Keiwen\Competition\Exception\CompetitionPlayerCountException;
use Keiwen\Competition\Exception\CompetitionRankingException;
use Keiwen\Competition\Exception\CompetitionRuntimeException;
use Keiwen\Competition\Game\AbstractGame;
use Keiwen\Competition\Game\GameDuel;
use Keiwen\Competition\Ranking\RankingDuel;
use Keiwen\Competition\Ranking\RankingsHolder;
use Keiwen\Utils\Math\Divisibility;

class CompetitionChampionshipDuel extends AbstractCompetition
{
    protected $serieCount;
    protected $shuffleCalendar;

    /** @var GameDuel[] $gameRepository */
    protected $gameRepository = array();

    /**
     * @param array $players
     * @param int $serieCount
     * @param bool $shuffleCalendar
     * @throws CompetitionPlayerCountException
     * @throws CompetitionRankingException
     */
    public function __construct(array $players, int $serieCount = 1, bool $shuffleCalendar = false)
    {
        if ($serieCount < 1) $serieCount = 1;
        $this->serieCount = $serieCount;
        $this->shuffleCalendar = $shuffleCalendar;
        parent::__construct($players);
    }

    public static function getMinPlayerCount(): int
    {
        return 3;
    }

    /**
     * @return RankingsHolder
     * @throws CompetitionRankingException
     */
    protected function initializeRankingsHolder(): RankingsHolder
    {
        return RankingDuel::generateDefaultRankingsHolder();
    }



    public function getSerieCount(): int
    {
        return $this->serieCount;
    }

    public function getShuffleCalendar(): bool
    {
        return $this->shuffleCalendar;
    }


    protected function generateCalendar(): void
    {
        if (Divisibility::isNumberEven($this->playerCount)) {
            $this->generateBaseCalendarEven();
        } else {
            $this->generateBaseCalendarOdd();
        }

        $roundInASerie = $this->getRoundCount();
        $this->generateFullCalendar();

        if ($this->shuffleCalendar) {
            // shuffle each serie individually instead of full calendar
            $calendarCopy = array_values($this->calendar);
            $this->calendar = array();
            $round = 1;
            for ($i = 1; $i <= $this->serieCount; $i++) {
                //for each serie, shuffle rounds inside
                //get calendar of current serie
                $calendarRandom = array_slice($calendarCopy, ($i - 1) * $roundInASerie, $roundInASerie);
                shuffle($calendarRandom);
                //shuffle and distribute again in actual calendar
                foreach ($calendarRandom as $randomRound => $oldRoundGames) {
                    $this->calendar[$round] = $calendarRandom[$randomRound];
                    foreach ($this->calendar[$round] as $newRoundGames) {
                        /** @var AbstractGame $newRoundGames */
                        $newRoundGames->setCompetitionRound($round);
                    }
                    $round++;
                }
            }
        }
    }

    protected function generateBaseCalendarEven()
    {
        $this->roundCount = $this->playerCount - 1;
        // for each round, first player will encounter all other in ascending order
        for ($round = 1; $round <= $this->roundCount; $round++) {
            $this->addGame($round, $this->getPlayerKeyOnSeed(1), $this->getPlayerKeyOnSeed($round + 1));
        }
        // init round when match next player
        $roundWhenMatchNextPlayer = 1;
        // starting next player, until we reach the penultimate (< instead of <= in loop)
        for ($seedHome = 2; $seedHome < $this->playerCount; $seedHome++) {
            // first match is on round following the round when this player matched previous player
            $round = $this->roundGapInCalendar($roundWhenMatchNextPlayer, 1);
            // first match is with the last one
            $this->addGame($round, $this->getPlayerKeyOnSeed($seedHome), $this->getPlayerKeyOnSeed($this->playerCount));

            // then match in ascending order with all others, starting with next player
            // stop before the last one, as already matched just before (< instead of <= in loop condition)
            // also store the round when we will match next player (so next of this one) to handle next player
            $roundWhenMatchNextPlayer = $this->roundGapInCalendar($round, 1);
            for ($seedAway = $seedHome + 1; $seedAway < $this->playerCount; $seedAway++) {
                $round = $this->roundGapInCalendar($round, 1);
                $this->addGame($round, $this->getPlayerKeyOnSeed($seedHome), $this->getPlayerKeyOnSeed($seedAway));
            }
        }
    }

    protected function generateBaseCalendarOdd()
    {
        $this->roundCount = $this->playerCount;
        // for each round, one player is out. We decided to go descendant order
        // the last player will not play on first round, the first will not play on last round

        $round = 1;
        // for each player
        for ($seedHome = 1; $seedHome <= $this->playerCount; $seedHome++) {
            // initialize $seedAway
            $seedAway = $seedHome;
            // one game per other player
            for ($i = 1; $i <= ($this->playerCount - 1); $i++) {
                // get seed - 2 for each game.
                $seedAway = $this->seedGapInPlayers($seedAway, -2);
                // If opponent seed is lower, means that this match should be already done
                // in that case, advance to next step (next round next opponent)
                if ($seedHome < $seedAway) {
                    $this->addGame($round, $this->getPlayerKeyOnSeed($seedHome), $this->getPlayerKeyOnSeed($seedAway));
                }
                $round = $this->roundGapInCalendar($round, 1);
            }
        }
    }

    protected function generateFullCalendar()
    {
        if ($this->serieCount == 1) return;
        // more than 1 serie, repeat base calendar for each other series
        $round = $this->roundCount + 1;
        // copy current calendar as a base
        $baseCalendar = $this->calendar;
        // for each serie
        for ($serie = 2; $serie <= $this->serieCount; $serie++) {
            // for each round of base calendar
            foreach ($baseCalendar as $baseRound => $gamesOfRound) {
                // for each games
                foreach ($gamesOfRound as $game) {
                    /** @var GameDuel $game */
                    // add a copy for a new round but switch home/away for each round
                    $reverse = (Divisibility::isNumberEven($serie) && Divisibility::isNumberOdd($baseRound))
                        || (Divisibility::isNumberOdd($serie) && Divisibility::isNumberEven($baseRound));
                    // unless if total series are odd, reverse even series only as it will not be fair anyway
                    // this way first seeds will be prioritized
                    if (Divisibility::isNumberOdd($this->serieCount)) {
                        $reverse = Divisibility::isNumberEven($serie);
                    }
                    if ($reverse) {
                        $this->addGame($round, $game->getKeyAway(), $game->getKeyHome());
                    } else {
                        $this->addGame($round, $game->getKeyHome(), $game->getKeyAway());
                    }
                }
                $round++;
            }
        }

        // after this, also switch home/away for first serie only if total series are even
        // here roundCount is still equal to first serie rounds
        if (Divisibility::isNumberEven($this->serieCount)) {
            for ($round = 1; $round <= $this->roundCount; $round++) {
                if (Divisibility::isNumberEven($round)) {
                    foreach ($this->calendar[$round] as $firstSerieGame) {
                        /** @var GameDuel $firstSerieGame */
                        $firstSerieGame->reverseHomeAway();
                    }
                }
            }
        }

        $this->roundCount = $this->roundCount * $this->serieCount;
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

    public function getMaxGameCountByPlayer($playerKey = null): int
    {
        $baseCount = parent::getMaxGameCountByPlayer($playerKey);
        if (Divisibility::isNumberOdd($this->playerCount)) {
            // if odd number, each player play one game less that actual round number, for each series!
            $baseCount -= $this->serieCount;
        }
        return $baseCount;
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

    /**
     * @param CompetitionChampionshipDuel $competition
     * @param bool $ranked
     * @return CompetitionChampionshipDuel
     * @throws CompetitionPlayerCountException
     * @throws CompetitionRankingException
     */
    public static function newCompetitionWithSamePlayers(AbstractCompetition $competition, bool $ranked = false): AbstractCompetition
    {
        $newCompetition = new CompetitionChampionshipDuel($competition->getPlayers($ranked), $competition->getSerieCount(), $competition->getShuffleCalendar());
        $newCompetition->setTeamComposition($competition->getTeamComposition());
        return $newCompetition;
    }

}

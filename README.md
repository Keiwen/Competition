# Competition
A PHP library that allow competition management

## Games
A 'game' is played with multiple players. They are several type of games:
* duel: opposition of only 2 players. The highest score win the game
* race: opposition of 2..n players. All players are ranked from 1 to n, the first win the game.
* brawl: opposition of 2..n players. Players are not ranked as in race, but only 1 players is defined as the winner of the game.
* performance: opposition of 2..n players. Each player scores into 1 or several performances, the highest sum win the game.

## Competition types
A competition is a set of games (all of the same type) managed by common rules.
They are several type of competition:
* Duels championship: Each round, each player have a duel versus a single opponent (= round robin)
* Races championship: Each round, players race all together, ending in an ordered list of final positions
* Brawls championship: Each round, players brawl all together, ending in a unique winner
* Performances championship: Each round, players register their best performances
* Contest: Each round, players register their best performances. The lowest are eliminated for next round
* Threshold competition: Each round, players register their best performances. Players reaching a minimum performance are qualified for next round
* Bubble championship: Each round, each player have a duel versus a single opponent ranked directly above or under him. The winner swap to highest rank. Points does not matter in this competition
* Swiss system: Each round, each player have a duel versus a single opponent with similar rank. This system features considerably fewer rounds that classic duel championship
* Tournament: Each round, each player have a duel versus a single opponent. The looser is eliminated and competition continue until a final player remain. On first round, high seeds will face low seeds. Highest seeds are dispatched so they cannot face each other on early rounds. This competition is designed for a power of 2 number of players (4, 8, 16, 32, ...). Elsewise, a play-in round is added first to eliminate excess number
* Double elimination tournament: Similar to classic tournament, except that players are eliminated after a second loss only. After first loss, they fall down to a loser bracket for their 'second chance'. Play-in are not supported for this competition
* Swap tournament: Each round, a player have a duel versus a single opponent ranked directly above or under him. The winner swap to highest rank. At the end of each round, first seed and last seed still in competition earned their final spot and will not be included in further rounds. Points does not matter in this competition. This competition is designed to have limited number of player, as winner is decided on first round, and the next only refines middle spots
* Gauntlet tournament: Each round, last 2 seeds remaining have a duel. The loser earn its final spot while the winner will face the next seed, and so on until the top seed to determine the winner. Points does not matter in this competition. This competition is designed to advantage best seeds.

Competition have a pre-determined number of round, which can contains several game but each player is affected to only one of them. 
Player rankings are available during the competition progress and updated after each game.

## Teams
On a competition, you can freely define team compositions, giving the list of players that belongs to each team.
This way, you can retrieve rankings for teams as well.

## Group, phase and tree
Sometimes we may need to mix different types of competitions,
and we often see a first part with round robin then a second part as a tournament with the best players of the first part.
This is managed in 'trees', where each part are called 'phase'.
Phases cannot be played in parallel, only one after the other.
Each phase contains 1..n group(s) that are defined as a basic competition part.
In previous example, we have have a first phase with x 'duels championship' competition inside,
then a second phase with one 'Tournament' competition.

During a phase, each groups are played in parallel: all games of round 1 should be done in all group before going to any game of round 2.

When a phase ends, there are rules defining which players may be picked or not in following phase.

## Builders and iterations
Builders are object that can prepare the competition.
Here will be defined the ground rules of the competition
(for example, points given for a win, loss or drawn).
There are builders for group (or simple competition), phase and tree.

When all rules are set, you can start an 'iteration':
defining the list of starting players, you can start to play games and set results.
When a first iteration ends, you can simply reuse the same builder to start a second iteration:
no need to set rules again.

## ELO
Competition supports ELO system for players, as a single integer value that can be updated or using the Keiwen\Utils\Elo\EloRating object.
If teams are defined, you can retrieve its ELO as the average ELO of its players.

## Performances and expenses
In this library, all competitions are supporting performances and expenses.
These are totally optional and can be defined freely when creating a competition.

Performances are data collected on each game for each player (for example, number of chocolate bars eaten during the game).
Totals will be used for rankings tie-breaker.
They are not determinant for the result of the game, except if explicitly set as this for competitions using performance games.

Expenses are collected on each game for each player (for example money spent in pyro for player entrance).
Remaining budgets will be used for rankings tie-breaker.


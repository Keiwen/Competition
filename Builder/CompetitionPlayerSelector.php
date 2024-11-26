<?php

namespace Keiwen\Competition\Builder;

class CompetitionPlayerSelector
{

    /** @var string $phaseName */
    protected $phaseName = '';
    /** @var string $playerPackName */
    protected $playerPackName = '';
    /** @var string $pickupMethod */
    protected $pickupMethod;
    /** @var int $startPickAtRank */
    protected $startPickAtRank = 1;
    /** @var int $selectionLength */
    protected $selectionLength = 0;

    CONST PICKUP_METHOD_BYGROUP = 'by_group';
    CONST PICKUP_METHOD_BYRANKINGROUP = 'by_rank_in_group';
    CONST PICKUP_METHOD_BYRANKSHUFFLED = 'by_rank_shuffled';
    CONST PICKUP_METHOD_BYRANKINPHASE = 'by_rank_in_phase';


    /**
     * Define a selector so that players can be selected, from the parent tree, to start the phase.
     * If needed, define limits low and high to restrict players picked.
     * @param string $phaseName optional: a specific phase name. If empty, take the previous phase. If no previous phase, consider the unused list of player in tree (all players for first phase)
     * @param string $playerPackName optional: see CompetitionBuilderTree::PLAYER_PACK_* constants. If empty, take unused + qualified of given phase. Always exclude players marked as eliminated in previous phase
     */
    public function __construct(string $phaseName = '', string $playerPackName = '')
    {
        $this->phaseName = $phaseName;
        $this->playerPackName = $playerPackName;
        $this->pickupMethod = static::PICKUP_METHOD_BYGROUP;
    }

    public static function getPickupMethods(): array
    {
        return [
            static::PICKUP_METHOD_BYGROUP,
            static::PICKUP_METHOD_BYRANKINGROUP,
            static::PICKUP_METHOD_BYRANKSHUFFLED,
            static::PICKUP_METHOD_BYRANKINPHASE,
        ];
    }

    /**
     * @return string
     */
    public function getPhaseName(): string
    {
        return $this->phaseName;
    }

    /**
     * @param string $phaseName
     * @return $this
     */
    public function setPhaseName(string $phaseName): self
    {
        $this->phaseName = $phaseName;
        return $this;
    }

    /**
     * @return string
     */
    public function getPlayerPackName(): string
    {
        return $this->playerPackName;
    }

    /**
     * @param string $playerPackName
     * @return $this
     */
    public function setPlayerPackName(string $playerPackName): self
    {
        if ($playerPackName === '' || in_array($playerPackName, CompetitionBuilderTree::getPlayerPacks())) {
            $this->playerPackName = $playerPackName;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getPickupMethod(): string
    {
        return $this->pickupMethod;
    }

    /**
     * Use PICKUP_METHOD_* constants
     * PICKUP_METHOD_BYGROUP will pick all players from 1st group, all players from 2nd group, ...
     * PICKUP_METHOD_BYRANKINGROUP will pick all first players from all groups, then all 2nd players from all groups, ...
     * PICKUP_METHOD_BYRANKSHUFFLED will pick all first players from all groups shuffled, then all 2nd players from all groups shuffled, ...
     * PICKUP_METHOD_BYRANKINPHASE will pick all players but mixing and re-ordering their rankings, ignoring original groups
     * @param string $pickupMethod
     * @return $this
     */
    public function setPickupMethod(string $pickupMethod): self
    {
        if (in_array($pickupMethod, static::getPickupMethods())) {
            $this->pickupMethod = $pickupMethod;
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getStartPickAtRank(): int
    {
        return $this->startPickAtRank;
    }

    /**
     * @param int $startPickAtRank >= 1
     * @return $this
     */
    public function setStartPickAtRank(int $startPickAtRank): self
    {
        // cannot start pick before 1
        if ($startPickAtRank < 1) $startPickAtRank = 1;
        $this->startPickAtRank = $startPickAtRank;
        return $this;
    }

    /**
     * @return int 0 will get all players
     */
    public function getSelectionLength(): int
    {
        return $this->selectionLength;
    }

    /**
     * @param int $selectionLength 0 to get all players
     * @return $this
     */
    public function setSelectionLength(int $selectionLength = 0): self
    {
        // cannot have a length < 1 (except 0)
        if ($selectionLength < 1) $selectionLength = 0;
        $this->selectionLength = $selectionLength;
        return $this;
    }




}

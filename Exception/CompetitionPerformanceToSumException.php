<?php

namespace Keiwen\Competition\Exception;

class CompetitionPerformanceToSumException extends CompetitionException
{

    public function __construct()
    {
        parent::__construct('Cannot create competition without performance to sum');
    }

}

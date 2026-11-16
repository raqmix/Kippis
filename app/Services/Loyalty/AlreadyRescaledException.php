<?php

namespace App\Services\Loyalty;

/** Thrown inside the rescale transaction when another run has already recorded its marker. */
class AlreadyRescaledException extends \RuntimeException
{
}

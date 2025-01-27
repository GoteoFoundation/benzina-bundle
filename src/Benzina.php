<?php

namespace Goteo\BenzinaBundle;

use Goteo\BenzinaBundle\Pump\PumpInterface;
use Goteo\BenzinaBundle\Stream\StreamInterface;

class Benzina
{
    /** @var PumpInterface[] */
    private array $availablePumps = [];

    public function __construct(
        iterable $instanceof,
    ) {
        $this->availablePumps = \iterator_to_array($instanceof);
    }

    /**
     * Get the Pumps that can process a sample in the stream data.
     *
     * @var mixed
     *
     * @return PumpInterface[]
     */
    public function getPumpsFor(StreamInterface $stream, int $sampleSize = 1): array
    {
        $sample = $stream->read($sampleSize);
        $stream->rewind();

        $pumps = [];
        foreach ($this->availablePumps as $pump) {
            if (!$pump->supports($sample)) {
                continue;
            }

            $pumps[] = $pump;
        }

        return $pumps;
    }
}
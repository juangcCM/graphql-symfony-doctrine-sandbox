<?php

namespace AppBundle\GraphQL\Relay\Resolver;

use AppBundle\Entity\Faction;
use AppBundle\Entity\Repository\ShipRepository;
use Overblog\GraphQLBundle\Relay\Connection\Output\ConnectionBuilder;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class FactionResolver implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function resolveRebels()
    {
        $rebels = $this->getFactionByType(Faction::TYPE_REBELS);

        return $rebels;
    }

    public function resolveEmpire()
    {
        $empire = $this->getFactionByType(Faction::TYPE_EMPIRE);

        return $empire;
    }

    public function resolveFake()
    {
        $fake = $this->getFactionByType(Faction::TYPE_FAKE);

        return $fake;
    }

    public function resolveShips(Faction $faction, $args)
    {
        //The old way
        //$ships = $faction->getShips()->toArray();
        //$connection = ConnectionBuilder::connectionFromArray($ships, $args);
        //$connection->sliceSize = count($connection->edges);
        //return $connection;

        /** @var ShipRepository $repository */
        $repository = $this->container
            ->get('doctrine.orm.default_entity_manager')
            ->getRepository('AppBundle:Ship');

        $arrayLength = $repository->countAllByFactionId($faction->getId());

        //--------------------------------------------------------------------------------------------------------------
        //todo move in vendor ?
        $beforeOffset = ConnectionBuilder::getOffsetWithDefault($args['before'], $arrayLength);
        $afterOffset = ConnectionBuilder::getOffsetWithDefault($args['after'], -1);

        $startOffset = max($afterOffset, -1) + 1;
        $endOffset = min($beforeOffset, $arrayLength);

        if (is_numeric($args['first'])) {
            $endOffset = min($endOffset, $startOffset + $args['first']);
        }
        if (is_numeric($args['last'])) {
            $startOffset = max($startOffset, $endOffset - $args['last']);
        }
        $offset = max($startOffset, 0);
        $limit = $endOffset - $startOffset;
        //--------------------------------------------------------------------------------------------------------------

        $shipsIDs = $repository->retrieveShipsIDsByFactionId($faction->getId(), $offset, $limit);

        $onFulFilled = function ($ships) use ($offset, $arrayLength, $args) {
            $connection = ConnectionBuilder::connectionFromArraySlice(
                $ships,
                $args,
                [
                    'sliceStart' => $offset,
                    'arrayLength' => $arrayLength,
                ]
            );
            $connection->sliceSize = count($ships);
        };

        if (empty($shipsIDs)) {
            return $onFulFilled([]);
        }

        $promise = $this->container->get('ships_loader')->loadMany($shipsIDs)->then($onFulFilled);

        return $promise;
    }

    private function getFactionByType($type)
    {
        return $this->container->get('doctrine.orm.default_entity_manager')
            ->getRepository('AppBundle:Faction')->findOneBy(['type' => $type]);
    }
}

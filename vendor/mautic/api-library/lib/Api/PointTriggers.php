<?php
/**
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 *
 * @see        http://mautic.org
 *
 * @license     MIT http://opensource.org/licenses/MIT
 */

namespace Mautic\Api;

/**
 * PointTriggers Context.
 */
class PointTriggers extends Api
{
    /**
     * {@inheritdoc}
     */
    protected $endpoint = 'points/triggers';

    /**
     * {@inheritdoc}
     */
    protected $listName = 'triggers';

    /**
     * {@inheritdoc}
     */
    protected $itemName = 'trigger';

    /**
     * {@inheritdoc}
     */
    protected $searchCommands = [
        'ids',
    ];

    /**
     * Remove events from a point trigger.
     *
     * @param int $triggerId
     *
     * @return array|mixed
     */
    public function deleteTriggerEvents($triggerId, array $eventIds)
    {
        return $this->makeRequest($this->endpoint.'/'.$triggerId.'/events/delete', ['events' => $eventIds], 'DELETE');
    }

    /**
     * Get list of available event types.
     *
     * @return array|mixed
     */
    public function getEventTypes()
    {
        return $this->makeRequest($this->endpoint.'/events/types');
    }
}

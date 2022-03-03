<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * The EventDispatcherInterface is the central point of Symfony's event listener system.
 * Listeners are registered on the manager and events are dispatched through the
 * manager.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
interface Symfony_EventDispatcher_EventDispatcherInterface
{
    /**
     * Dispatches an event to all registered listeners.
     *
     * @param string                        $eventName The name of the event to dispatch. The name of
     *                                                 the event is the name of the method that is
     *                                                 invoked on listeners.
     * @param Symfony_EventDispatcher_Event $event     The event to pass to the event handlers/listeners.
     *                                                 If not supplied, an empty Event instance is created.
     *
     * @return Symfony_EventDispatcher_Event
     *
     * @api
     */
    public function dispatch($eventName, Symfony_EventDispatcher_Event $event = null);

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string   $eventName The event to listen on
     * @param callable $listener  The listener
     * @param int      $priority  The higher this value, the earlier an event
     *                            listener will be triggered in the chain (defaults to 0)
     *
     * @api
     */
    public function addListener($eventName, $listener, $priority = 0);

    /**
     * Adds an event subscriber.
     *
     * The subscriber is asked for all the events he is
     * interested in and added as a listener for these events.
     *
     * @param Symfony_EventDispatcher_EventSubscriberInterface $subscriber The subscriber.
     *
     * @api
     */
    public function addSubscriber(Symfony_EventDispatcher_EventSubscriberInterface $subscriber);

    /**
     * Removes an event listener from the specified events.
     *
     * @param string   $eventName The event to remove a listener from
     * @param callable $listener  The listener to remove
     */
    public function removeListener($eventName, $listener);

    /**
     * Removes an event subscriber.
     *
     * @param Symfony_EventDispatcher_EventSubscriberInterface $subscriber The subscriber
     */
    public function removeSubscriber(Symfony_EventDispatcher_EventSubscriberInterface $subscriber);

    /**
     * Gets the listeners of a specific event or all listeners.
     *
     * @param string $eventName The name of the event
     *
     * @return array The event listeners for the specified event, or all event listeners by event name
     */
    public function getListeners($eventName = null);

    /**
     * Checks whether an event has any registered listeners.
     *
     * @param string $eventName The name of the event
     *
     * @return bool    true if the specified event has any listeners, false otherwise
     */
    public function hasListeners($eventName = null);
}

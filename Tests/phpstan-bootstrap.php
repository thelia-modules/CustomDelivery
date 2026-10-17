<?php

/*
 * Loaded by phpstan-baseline.neon. CustomDelivery implements Thelia\Module\DeliveryDateAwareInterface,
 * which only a core with delivery dates has: the module aliases an empty stand-in when it is missing.
 * PHPStan does not follow that alias and reports the class as implementing an unknown interface, an
 * error no baseline entry can ignore. Declaring the empty interface here, only when the core has none,
 * stands in for the alias during the analysis.
 */

namespace Thelia\Module;

if (!interface_exists(DeliveryDateAwareInterface::class)) {
    interface DeliveryDateAwareInterface
    {
    }
}

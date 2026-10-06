<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace CustomDelivery\Compat;

/**
 * Stands in for Thelia\Module\DeliveryDateAwareInterface on a core that predates delivery
 * dates, so that the module class still loads there. Such a core never asks for the shapes
 * the carrier accepts, and the buyer is offered no date, as before.
 */
interface DeliveryDateAwareFallback
{
}

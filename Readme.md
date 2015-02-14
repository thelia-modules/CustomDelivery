# Custom delivery

With this module, you can use a flat fee for your areas.

This module is compatible with Thelia version 2.1 or greater. 

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is CustomDelivery.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/custom-delivery-module:~1.0
```

## Usage

Just enter the price you want for each area in the configuration page of the module.  
You can create as many slices you want. These slices are based on the amount price and/or the weight of an order. You 
can associate an optional taxe rule to the module to include taxes for the shipment.    


# customization 

You can customize the mails sent by the module in the **Mailing templates** configuration page in the back-office. The
 template used is called `mail_custom_delivery`.

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


namespace CustomDelivery\Form;

use CustomDelivery\CustomDelivery;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Form\BaseForm;
use Thelia\Model\Base\TaxRuleQuery;

/**
 * Class ConfigurationForm
 * @package VirtualProductGereso\Form
 * @author Julien Chanséaume <julien@thelia.net>
 */
class ConfigurationForm extends BaseForm
{
    public function checkTaxRuleId($value, ExecutionContextInterface $context)
    {
        if (0 !== intval($value)) {
            if (null === TaxRuleQuery::create()->findPk($value)) {
                $context->addViolation(
                    $this->trans(
                        "The Tax Rule id '%id' doesn't exist",
                        [
                            "%id" => $value,
                        ]
                    )
                );
            }
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "customdelivery-configuration-form";
    }

    protected function buildForm()
    {
        $form = $this->formBuilder;

        $config = CustomDelivery::getConfig();

        $form
            ->add(
                "url",
                "text",
                [
                    'constraints' => [
                        new NotBlank()
                    ],
                    'data' => $config['url'],
                    'label' => $this->trans("Tracking URL"),
                    'label_attr' => [
                        'for' => "url",
                        'help' => $this->trans(
                            "The tracking URL. %ID% will be replaced by the tracking number entered in the order"
                        )
                    ],
                ]
            )
            ->add(
                "method",
                "choice",
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual(['value' => 0])
                    ],
                    "choices" => [
                        CustomDelivery::METHOD_PRICE_WEIGHT => $this->trans("Price and weight"),
                        CustomDelivery::METHOD_PRICE => $this->trans("Price"),
                        CustomDelivery::METHOD_WEIGHT => $this->trans("Weight"),
                    ],
                    'data' => $config['method'],
                    'label' => $this->trans("Method"),
                    'label_attr' => [
                        'for' => "method",
                        'help' => $this->trans(
                            "The method used to select the right slice."
                        )
                    ],
                ]
            )
            ->add(
                "tax",
                "tax_rule_id",
                [
                    "constraints" => [
                        new Callback([
                            "methods" => [
                                [$this, "checkTaxRuleId"]
                            ]
                        ])
                    ],
                    'required' => false,
                    'data' => $config['tax'],
                    'label' => $this->trans("Tax rule"),
                    'label_attr' => [
                        'for' => "method",
                        'help' => $this->trans(
                            "The tax rule used to calculate postage taxes."
                        )
                    ],
                ]
            );
    }

    protected function trans($id, array $parameters = [])
    {
        return $this->translator->trans($id, $parameters, CustomDelivery::MESSAGE_DOMAIN);
    }
}

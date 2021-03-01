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
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Form\Type\Field\AreaIdType;
use Thelia\Form\BaseForm;
use Thelia\Type\FloatType;

/**
 * Class SliceForm
 * @author Julien Chanséaume <julien@thelia.net>
 */
class SliceForm extends BaseForm
{
    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return "customdelivery-configuration-form";
    }

    protected function buildForm()
    {
        $form = $this->formBuilder;

        $form
            ->add(
                "id",
                NumberType::class,
                [
                    'constraints' => [
                        new NotBlank()
                    ],
                    'label' => $this->trans("Id")
                ]
            )
            ->add(
                "area",
                AreaIdType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual(['value' => 0])
                    ],
                    'label' => $this->trans("Area"),
                ]
            )
            ->add(
                "priceMax",
                FloatType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual(['value' => 0])
                    ],
                    'label' => $this->trans("Area"),
                ]
            )
            ->add(
                "weightMax",
                FloatType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThanOrEqual(['value' => 0])
                    ],
                    'label' => $this->trans("Area"),
                ]
            );
    }

    protected function trans($id, array $parameters = [])
    {
        return $this->translator->trans($id, $parameters, CustomDelivery::MESSAGE_DOMAIN);
    }
}

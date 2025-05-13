<?php

namespace CustomDelivery\Service;

use CustomDelivery\Model\CustomDeliverySlice;
use CustomDelivery\Model\CustomDeliverySliceQuery;
use CustomDelivery\CustomDelivery;
use Thelia\Core\Translation\Translator;

class CustomDeliveryService
{
    /**
     * Crée ou met à jour un slice en validant les données reçues.
     *
     * @param array $data Données (id, area, priceMax, weightMax, price)
     * @return array [ 'success' => bool, 'messages' => array, 'slice' => ?CustomDeliverySlice ]
     */
    public function saveSlice(array $data): array
    {
        $messages = [];
        $config = CustomDelivery::getConfig();

        $id = (int) ($data['id'] ?? 0);
        $slice = CustomDeliverySliceQuery::create()->findPk($id) ?? new CustomDeliverySlice();

        // Validation areaId
        $areaId = (int) ($data['area'] ?? 0);
        if ($areaId <= 0) {
            $messages[] = Translator::getInstance()->trans(
                'The area is not valid',
                [],
                CustomDelivery::MESSAGE_DOMAIN
            );
        } else {
            $slice->setAreaId($areaId);
        }

        // Validation priceMax si méthode différente de poids
        if ($config['method'] !== CustomDelivery::METHOD_WEIGHT) {
            $priceMax = $this->toFloat($data['priceMax'] ?? 0);
            if ($priceMax <= 0) {
                $messages[] = Translator::getInstance()->trans(
                    'The price max value is not valid',
                    [],
                    CustomDelivery::MESSAGE_DOMAIN
                );
            } else {
                $slice->setPriceMax($priceMax);
            }
        }

        // Validation weightMax si méthode différente de prix
        if ($config['method'] !== CustomDelivery::METHOD_PRICE) {
            $weightMax = $this->toFloat($data['weightMax'] ?? 0);
            if ($weightMax <= 0) {
                $messages[] = Translator::getInstance()->trans(
                    'The weight max value is not valid',
                    [],
                    CustomDelivery::MESSAGE_DOMAIN
                );
            } else {
                $slice->setWeightMax($weightMax);
            }
        }

        // Validation price (>= 0)
        $price = $this->toFloat($data['price'] ?? 0);
        if ($price < 0) {
            $messages[] = Translator::getInstance()->trans(
                'The price value is not valid',
                [],
                CustomDelivery::MESSAGE_DOMAIN
            );
        } else {
            $slice->setPrice($price);
        }

        $success = empty($messages);

        if ($success) {
            $slice->save();
            $messages[] = Translator::getInstance()->trans(
                'Your slice has been saved',
                [],
                CustomDelivery::MESSAGE_DOMAIN
            );
        }

        return [
            'success' => $success,
            'messages' => $messages,
            'slice' => $success ? $slice : null,
        ];
    }

    /**
     * Supprime un slice par son ID.
     *
     * @param int $id
     * @return array ['success' => bool, 'messages' => array]
     */
    public function deleteSlice(int $id): array
    {
        $messages = [];

        if ($id <= 0) {
            $messages[] = Translator::getInstance()->trans(
                'The slice has not been deleted',
                [],
                CustomDelivery::MESSAGE_DOMAIN
            );

            return ['success' => false, 'messages' => $messages];
        }

        $slice = CustomDeliverySliceQuery::create()->findPk($id);

        if (null === $slice) {
            $messages[] = Translator::getInstance()->trans(
                'The slice was not found',
                [],
                CustomDelivery::MESSAGE_DOMAIN
            );

            return ['success' => false, 'messages' => $messages];
        }

        $slice->delete();

        return ['success' => true, 'messages' => $messages];
    }

    /**
     * Transforme une valeur en float en gérant le format européen.
     */
    protected function toFloat($val, float $default = -1): float
    {
        if (is_string($val) && preg_match('#^([\d.,]+)$#', $val, $matches)) {
            // Ex : "1.234,56" ou "1234,56" -> "1234.56"
            $number = str_replace(['.', ','], ['', '.'], $matches[1]);
            return (float) $number;
        }

        if (is_numeric($val)) {
            return (float) $val;
        }

        return $default;
    }
}

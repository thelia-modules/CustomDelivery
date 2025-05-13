<?php

namespace CustomDelivery\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use CustomDelivery\Model\Map\CustomDeliverySliceTableMap;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\State\PropelCollectionProvider;
use Thelia\Api\Bridge\Propel\State\PropelItemProvider;
use Thelia\Model\Area;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/custom-delivery/slices/{id}',
            name: 'api_custom_delivery_slices_get_id',
            provider: PropelItemProvider::class
        ),
        new GetCollection(
            uriTemplate: '/admin/custom-delivery/slices',
            name: 'api_custom_delivery_slices_get_collection',
            provider: PropelCollectionProvider::class
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
    denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]]
)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/custom-delivery/slices/{id}',
            name: 'api_custom_delivery_slices_get_id_front',
            provider: PropelItemProvider::class
        ),
        new GetCollection(
            uriTemplate: '/front/custom-delivery/slices',
            name: 'api_custom_delivery_slices_get_collection_front',
            provider: PropelCollectionProvider::class
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
    denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]]
)]
class CustomDeliverySlices
{
    public const GROUP_ADMIN_READ = 'admin:custom_delivery_slice:read';
    public const GROUP_ADMIN_WRITE = 'admin:custom_delivery_slice:write';
    public const GROUP_FRONT_READ = 'front:custom_delivery_slice:read';
    public const GROUP_FRONT_WRITE = 'front:custom_delivery_slice:write';

    /**
     * @var int|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    /**
     * @var Area|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    #[Relation(targetResource: Area::class)]
    public ?Area $area = null;

    /**
     * @var float|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?float $weightMax = null;

    /**
     * @var float|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?float $priceMax = null;

    /**
     * @var float|null
     */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE, self::GROUP_FRONT_READ])]
    public ?float $price = null;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     * @return void
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return Area|null
     */
    public function getArea(): ?Area
    {
        return $this->area;
    }

    /**
     * @param Area|null $area
     * @return void
     */
    public function setArea(?Area $area): void
    {
        $this->area = $area;
    }

    /**
     * @return float|null
     */
    public function getWeightMax(): ?float
    {
        return $this->weightMax;
    }

    /**
     * @param float|null $weightMax
     * @return void
     */
    public function setWeightMax(?float $weightMax): void
    {
        $this->weightMax = $weightMax;
    }

    /**
     * @return float|null
     */
    public function getPriceMax(): ?float
    {
        return $this->priceMax;
    }

    /**
     * @param float|null $priceMax
     * @return void
     */
    public function setPriceMax(?float $priceMax): void
    {
        $this->priceMax = $priceMax;
    }

    /**
     * @return float|null
     */
    public function getPrice(): ?float
    {
        return $this->price;
    }

    /**
     * @param float|null $price
     * @return void
     */
    public function setPrice(?float $price): void
    {
        $this->price = $price;
    }

    /**
     * @return TableMap|null
     */
    #[Ignore]
    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new CustomDeliverySliceTableMap();
    }

}

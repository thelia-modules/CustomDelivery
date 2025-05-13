<?php

namespace CustomDelivery\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use CustomDelivery\Model\Map\CustomDeliverySliceTableMap;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Thelia\Api\Bridge\Propel\State\PropelCollectionProvider;
use Thelia\Api\Bridge\Propel\State\PropelItemProvider;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\PropelResourceTrait;

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
)]
class CustomDeliverySlices implements PropelResourceInterface
{
    use PropelResourceTrait;

    public const GROUP_ADMIN_READ = 'admin:custom_delivery_slice:read';
    public const GROUP_FRONT_READ = 'front:custom_delivery_slice:read';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $areaId = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $weightMax = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $priceMax = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?float $price = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getAreaId(): ?int
    {
        return $this->areaId;
    }

    public function setAreaId(?int $areaId): self
    {
        $this->areaId = $areaId;
        return $this;
    }

    public function getWeightMax(): ?float
    {
        return $this->weightMax;
    }

    public function setWeightMax(?float $weightMax): self
    {
        $this->weightMax = $weightMax;
        return $this;
    }

    public function getPriceMax(): ?float
    {
        return $this->priceMax;
    }

    public function setPriceMax(?float $priceMax): self
    {
        $this->priceMax = $priceMax;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;
        return $this;
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

<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tblProductData')]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\Column(name: 'intProductDataId', type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'strProductName', type: 'string', length: 50)]
    public string $name {
        set(string $value) => $this->name = trim($value);
    }

    #[ORM\Column(name: 'strProductDesc', type: 'string', length: 255)]
    public string $description {
        set(string $value) => $this->description = trim($value);
    }

    #[ORM\Column(name: 'strProductCode', type: 'string', length: 10, unique: true)]
    public string $code {
        set(string $value) => $this->code = strtoupper(trim($value));
    }

    #[ORM\Column(name: 'intStock', type: 'integer', options: ['default' => 0])]
    public int $stock = 0;

    #[ORM\Column(name: 'intPricePence', type: 'integer')]
    private int $priceInPence = 0;

    public Money $price {
        get => Money::fromMinorUnits($this->priceInPence);
        set(Money $value) {
            $this->priceInPence = $value->amount;
        }
    }

    #[ORM\Column(name: 'dtmAdded', type: 'datetime_immutable', nullable: true)]
    public private(set) ?DateTimeImmutable $addedAt = null;

    #[ORM\Column(name: 'dtmDiscontinued', type: 'datetime_immutable', nullable: true)]
    public private(set) ?DateTimeImmutable $discontinuedAt = null;

    #[ORM\Column(name: 'stmTimestamp', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    public private(set) DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->addedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public static function fromPrimitives(
        string $code,
        string $name,
        string $description,
        int $stock,
        string $rawPrice,
    ): self {
        $product = new self();
        $product->code = $code;
        $product->name = $name;
        $product->description = $description;
        $product->stock = $stock;
        $product->price = Money::fromString($rawPrice);

        return $product;
    }

    public function markAsDiscontinued(DateTimeImmutable $at): void
    {
        $this->discontinuedAt = $at;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}

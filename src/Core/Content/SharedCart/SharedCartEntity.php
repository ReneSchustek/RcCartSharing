<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Core\Content\SharedCart;

use DateTimeInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Ein gespeicherter Warenkorb, wie er in `rc_shared_cart` steht. Die Klasse trägt nur Daten; was
 * beim Speichern, Laden und Aufräumen gilt, entscheiden die Dienste unter `Service/`.
 */
final class SharedCartEntity extends Entity
{
    use EntityIdTrait;

    protected string $token;

    protected ?string $name = null;

    protected string $salesChannelId;

    /** Leer bei einem Warenkorb, der ohne Anmeldung gespeichert wurde. */
    protected ?string $customerId = null;

    /**
     * Der Abzug aus {@see \Ruhrcoder\RcCartSharing\Service\CartSnapshotBuilder}, ohne Preise.
     *
     * @var list<array<string, mixed>>
     */
    protected array $lineItems = [];

    /** Leer heißt: läuft nie ab, die Aufräum-Aufgabe lässt den Datensatz stehen. */
    protected ?DateTimeInterface $expiresAt = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?CustomerEntity $customer = null;

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * @param list<array<string, mixed>> $lineItems
     */
    public function setLineItems(array $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getExpiresAt(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}

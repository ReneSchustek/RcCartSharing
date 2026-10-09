<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Core\Content\SharedCart;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * DAL-Definition für `rc_shared_cart`.
 *
 * Kein Feld trägt `ApiAware`. Der Abzug enthält die Kundeneingaben eines Warenkorbs, und die
 * Serialisierer der Admin- und Store-API lassen jedes Feld ohne diese Markierung weg. Gelesen wird
 * nur über den eigenen Storefront-Weg, der die Kennung verlangt.
 *
 * Die Spalte für die Kennung fasst 64 Zeichen, erzeugt werden 32 (siehe
 * {@see \Ruhrcoder\RcCartSharing\Service\ShareTokenGenerator::TOKEN_LENGTH}). Ungenutzter Platz
 * kostet in `VARCHAR` nichts, und eine längere Kennung bräuchte keine Migration.
 */
final class SharedCartDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'rc_shared_cart';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SharedCartEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SharedCartCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('token', 'token', 64))->addFlags(new Required()),
            new StringField('name', 'name'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))
                ->addFlags(new Required()),
            new FkField('customer_id', 'customerId', CustomerDefinition::class),
            (new JsonField('line_items', 'lineItems'))->addFlags(new Required()),
            new DateTimeField('expires_at', 'expiresAt'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}

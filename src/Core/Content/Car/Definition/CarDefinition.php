<?php

namespace TfcSwOzi\Core\Content\Car\Definition;

use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class CarDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'tfc_car';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
            new StringField('hsn', 'hsn'),
            new StringField('tsn', 'tsn'),
            new StringField('hersteller', 'hersteller'),
            new StringField('modell', 'modell'),
            new StringField('baujahr', 'baujahr'),
            new StringField('bemerkung', 'bemerkung'),
        ]);
    }
}







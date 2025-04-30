<?php
namespace TfcSwOzi\Core\Content\Car\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field;

class CarEntity extends Entity
{
    /** @var string */
    protected $hsn;

    /** @var string */
    protected $tsn;

    /** @var string */
    protected $hersteller;

    /** @var string */
    protected $modell;

    /** @var string */
    protected $baujahr;

    /** @var string */
    protected $bemerkung;

    // Getters and Setters
    public function getHsn(): string
    {
        return $this->hsn;
    }

    public function setHsn(string $hsn)
    {
        $this->hsn = $hsn;
    }

    public function getTsn(): string
    {
        return $this->tsn;
    }

    public function setTsn(string $tsn)
    {
        $this->tsn = $tsn;
    }

    public function getHersteller(): string
    {
        return $this->hersteller;
    }

    public function setHersteller(string $hersteller)
    {
        $this->hersteller = $hersteller;
    }

    public function getModell(): string
    {
        return $this->modell;
    }

    public function setModell(string $modell)
    {
        $this->modell = $modell;
    }

    public function getBaujahr(): string
    {
        return $this->baujahr;
    }

    public function setBaujahr(string $baujahr)
    {
        $this->baujahr = $baujahr;
    }

    public function getBemerkung(): string
    {
        return $this->bemerkung;
    }

    public function setBemerkung(string $bemerkung)
    {
        $this->bemerkung = $bemerkung;
    }

    // Entity Fields
    public static function defineFields(): FieldCollection
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

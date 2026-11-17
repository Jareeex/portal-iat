<?php

namespace App\Modules\Fis\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $ident
 * @property string $type
 * @property string $name
 * @property string|null $keywords
 * @property string|null $iso_country
 * @property string|null $country_name
 * @property string|null $municipality
 * @property string|null $icao_code
 * @property string|null $iata_code
 * @property bool $scheduled_service
 * @property-read string $code
 */
class Airport extends Model
{
    protected $table = 'fis_airports';

    protected $primaryKey = 'ident';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scheduled_service' => 'boolean'];
    }

    public function getCodeAttribute(): string
    {
        return $this->iata_code ?: ($this->icao_code ?: $this->ident);
    }
}

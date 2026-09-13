<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\ApiModels;

use Sashalenz\UkrPostApi\ApiModels\ResponseData\PostPayTransferData;

final class Transfer extends BaseModel
{
    public function postPayStatus(string $barcode): PostPayTransferData
    {
        return PostPayTransferData::from($this->get(
            '/transfers/shipment-postpays/'.rawurlencode($barcode).'/with-recipient',
        )->all());
    }
}

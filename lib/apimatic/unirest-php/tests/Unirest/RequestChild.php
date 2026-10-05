<?php

namespace Ecard\Cms\Dependencies\Unirest;

class RequestChild extends Request
{
    public function getTotalNumberOfConnections()
    {
        return $this->totalNumberOfConnections;
    }

    public function resetHandle()
    {
        $this->initializeHandle();
    }
}

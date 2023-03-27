<?php

namespace App\Domains\Company\Events;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;

class UserJoinedCompanyEvent
{
    private $company;

    private $companyMember;

    private $code;

    public function __construct(Company $company, CompanyMember $companyMember, string $code = null)
    {
        $this->company       = $company;
        $this->companyMember = $companyMember;
        $this->code          = $code;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getCompanyMember(): CompanyMember
    {
        return $this->companyMember;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }
}

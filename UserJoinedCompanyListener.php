<?php

namespace App\Domains\Company\Listeners;

use App\Domains\Company\Events\UserJoinedCompanyBroadcastEvent;
use App\Domains\Company\Events\UserJoinedCompanyEvent;
use App\Domains\Company\Models\CompanyMember;

class UserJoinedCompanyListener
{
    public function handle(UserJoinedCompanyEvent $userJoinedCompanyEvent)
    {
        $company    = $userJoinedCompanyEvent->getCompany();
        $joinedMember = $userJoinedCompanyEvent->getCompanyMember();

        /** @var CompanyMember $member */
        foreach ($company->active_members as $member) {
            if ($member->getKey() === $joinedMember->getKey()) {
                continue;
            }

            broadcast(
                new UserJoinedCompanyBroadcastEvent($joinedMember, $member)
            );
        }
    }
}

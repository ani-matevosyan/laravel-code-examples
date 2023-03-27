<?php

namespace App\Domains\Company\Events;

use App\Domains\Company\Models\CompanyMember;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UserJoinedCompanyBroadcastEvent implements ShouldBroadcast
{
    use InteractsWithSockets;

    private $company;

    private $joinedMember;

    private $notifiableMember;

    public function __construct(CompanyMember $joinedMember, CompanyMember $notifiableMember)
    {
        $this->joinedMember     = $joinedMember;
        $this->notifiableMember = $notifiableMember;
        $this->company          = $joinedMember->company;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("user.{$this->notifiableMember->getUserId()}");
    }

    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->company->getKey(),
            'user_id'    => $this->joinedMember->getUserId(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.company.joined';
    }
}
